<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../../../_assets/configuration/config.php';
require_once __DIR__ . '/../_helpers/accountHelper.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_shares/webfilemanager_shares.php';
require_once __DIR__ . '/../../../_assets/libs/vendor/autoload.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::GET);
Request::DenyIfDirectRequest(__FILE__);
#endregion

function GetFile(array $path): never
{
    $formattedPath = '/' . implode('/', $path);
    if (file_exists($formattedPath) && is_file($formattedPath))
    {
        // Logger::Log('Getting contents of file: ' . $formattedPath, LogLevel::DEBUG);
        $fileStream = new FileStream($formattedPath, array_key_exists('download', Request::Get()));
        $fileStream->Begin();
        // exit; //This is called from the end function which is called from the begin function above.
    }
    else if (str_ends_with(basename($formattedPath), '.thumbnail.png'))
    {
        Request::SendError(501);
        //I can reuse the encryption function here to get a random name for the file but have it still be reversible (also this encryption method produces a string which is valid for a file name).
        $thumbnailPath = __DIR__ . '/_storage/thumbnails' . AccountHelper::Crypt(true, basename($formattedPath), $formattedPath) . '.thumbnail.png';
        if (file_exists($thumbnailPath) && is_file($thumbnailPath))
        {
            $fileStream = new FileStream($thumbnailPath, array_key_exists('download', Request::Get()));
            $fileStream->Begin();
        }
        else
        {
            $originalFile = dirname($formattedPath) . '/' . str_replace('.thumbnail.png', '', basename($formattedPath));
            if (!file_exists($originalFile) || !is_file($originalFile)) { Request::SendError(404); }

            try
            {
                $ffmpeg = FFMpeg\FFMpeg::create(array(
                    'ffmpeg.binaries' => Config::Config()['ffmpeg']['binaries']['ffmpeg'],
                    'ffprobe.binaries' => Config::Config()['ffmpeg']['binaries']['ffprobe']
                ));
                $video = $ffmpeg->open($originalFile);
                $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(1));
                $frame->save($thumbnailPath);

                //Resize the image if larger than the given dimensions.
                $targetWidth = Config::Config()['ffmpeg']['options']['thumbnail']['width'];
                $targetHeight = Config::Config()['ffmpeg']['options']['thumbnail']['height'];
                list($originalWidth, $originalHeight) = getimagesize($thumbnailPath);
                if ($originalWidth > $targetWidth || $originalHeight > $targetHeight)
                {
                    $ratio = $originalWidth / $originalHeight;
                    if ($targetWidth / $targetHeight > $ratio)
                    {
                        $newWidth = $targetHeight * $ratio;
                        $newHeight = $targetHeight;
                    }
                    else
                    {
                        $newHeight = $targetWidth / $ratio;
                        $newWidth = $targetWidth;
                    }

                    if (
                        ($src = imagecreatefromjpeg($thumbnailPath)) === false ||
                        ($dst = imagecreatetruecolor($newWidth, $newHeight)) === false ||
                        !imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight) ||
                        !imagejpeg($dst, $thumbnailPath)
                    )
                    { Request::SendError(500, ErrorMessages::THUMBNAL_ERROR); }
                }

                $fileStream = new FileStream($thumbnailPath, array_key_exists('download', Request::Get()));
                $fileStream->Begin();
            }
            catch (Exception $e)
            {
                Logger::Log('Error generating thumbnail: ' . $e->getMessage(), LogLevel::ERROR);
                Request::SendError(500, ErrorMessages::THUMBNAL_ERROR);
            }
        }
    }
    else
    {
        Request::SendError(400, ErrorMessages::INVALID_PATH);
    }
}

#region Get root paths (dosen't require authentication as it's never sent to the client without authentication)
$pathsTable = new webfilemanager_paths(
    true,
    Config::Config()['database']['host'],
    Config::Config()['database']['database'],
    Config::Config()['database']['username'],
    Config::Config()['database']['password']
);
$pathsResponse = $pathsTable->Select(array());
if ($pathsResponse === false)
{
    Logger::Log(
        array(
            $pathsTable->GetLastException(),
            $pathsTable->GetLastSQLError()
        ),
        LogLevel::ERROR
    );
    Request::SendError(500);
}
$roots = array();
foreach ($pathsResponse as $path)
{
    $roots[$path->web_path] = $path->local_path;
}
#endregion

$path = array_slice(Request::URLStrippedRoot(), 3);
if (!empty($path))
{
    if (array_key_exists($path[0], $roots))
    {
        if (
            !isset(Request::Get()['id']) ||
            !isset(Request::Get()['token'])
        )
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        $accountHelper = new AccountHelper();
        $accountResult = $accountHelper->VerifyToken(
            Request::Get()['id'],
            Request::Get()['token']
        );
        if ($accountResult === false)
        { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

        $searchPath = array_merge(
            array_filter(explode('/', $roots[$path[0]]), fn($part) => !ctype_space($part) && $part !== ''),
            array_filter(array_slice($path, 1), fn($part) => !ctype_space($part) && $part !== '')
        );
        GetFile($searchPath);
    }
    else
    {
        $sharesTable = new webfilemanager_shares(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
        $sharesResponse = $sharesTable->Select(array(
            'id' => $path[0]
        ));
        if ($sharesResponse === false)
        {
            Logger::Log(
                array(
                    $sharesTable->GetLastException(),
                    $sharesTable->GetLastSQLError()
                ),
                LogLevel::ERROR
            );
            Request::SendError(500);
        }
        else if (empty($sharesResponse))
        { Request::SendError(404); }

        $share = $sharesResponse[0];
        $searchPath = array();
        foreach ($pathsResponse as $dbPath)
        {
            if ($dbPath->id == $share->pid)
            {
                $searchPath = array_merge(
                    array_filter(explode('/', $dbPath->local_path), fn($part) => !ctype_space($part) && $part !== ''),
                    array_filter(explode('/', $share->path), fn($part) => !ctype_space($part) && $part !== ''),
                    array_filter(array_slice($path, 1), fn($part) => !ctype_space($part) && $part !== '')
                );
                break;
            }
        }
        switch ($share->share_type)
        {
            case 0:
                //Public.
                GetFile($searchPath);
            case 1:
                //Public with timeout.
                if (time() > $share->expiry_time)
                { Request::SendError(403); }
                GetFile($searchPath);
            default:
                Request::SendError(500);
        }
    }
}
else
{
    Request::SendError(400, ErrorMessages::INVALID_PATH);
}

//https://stackoverflow.com/questions/1628260/downloading-a-file-with-a-different-name-to-the-stored-name
//Tweaked from: http://codesamplez.com/programming/php-html5-video-streaming-tutorial
class FileStream
{
    private $path = "";
    private $name = "";
    private $extension = "";
    private $download = false;
    private $stream = "";
    private $buffer = 102400;
    private $start = -1;
    private $end = -1;
    private $size = 0;
 
    function __construct(string $filePath, bool $download = false)
    {
        $this->path = $filePath;
        $pathInfo = pathinfo($filePath);
        $this->name = $pathInfo['filename'];
        $this->extension = $pathInfo['extension'];
        $this->download = $download;
    }
     
    private function Open()
    {
        if (!($this->stream = fopen($this->path, 'rb')))
        {
            http_response_code(500);
            die('Could not open stream for reading');
        }
    }
     
    private function SetHeaders()
    {
        ob_get_clean();
        header("Content-Type: " . mime_content_type($this->path));
        header("Content-Disposition: " . ($this->download ? 'attachment' : 'inline') . "; filename=\"" . $this->name . "." . $this->extension . "\"");
        header("Cache-Control: max-age=2592000, public");
        header("Expires: " . gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT');
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', @filemtime($this->path)) . ' GMT' );
        $this->start = 0;
        $this->size = filesize($this->path); 
        $this->end = $this->size - 1;
        header("Accept-Ranges: 0-" . $this->end);
         
        if (isset($_SERVER['HTTP_RANGE']))
        {
            $c_start = $this->start;
            $c_end = $this->end;
 
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false)
            {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                http_response_code(416);
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            if ($range == '-')
            {
                $c_start = $this->size - substr($range, 1);
            }
            else
            {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $this->end) ? $this->end : $c_end;
            if ($c_start > $c_end || $c_start > $this->size - 1 || $c_end >= $this->size)
            {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                http_response_code(416);
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            $this->start = $c_start;
            $this->end = $c_end;
            $length = $this->end - $this->start + 1;
            fseek($this->stream, $this->start);
            header('HTTP/1.1 206 Partial Content');
            http_response_code(206);
            header("Content-Length: " . $length);
            header("Content-Range: bytes $this->start-$this->end/" . $this->size);
        }
        else
        {
            header("Content-Length: " . $this->size);
            http_response_code(200);
        }  
    }
    
    //End the file stream.
    private function End()
    {
        fclose($this->stream);
        exit;
    }
     
    //Stream the data to the client.
    private function Stream()
    {
        $i = $this->start;
        set_time_limit(0);
        while(!feof($this->stream) && $i <= $this->end)
        {
            $bytesToRead = $this->buffer;
            if(($i+$bytesToRead) > $this->end)
            {
                $bytesToRead = $this->end - $i + 1;
            }
            $data = fread($this->stream, $bytesToRead);
            echo $data;
            flush();
            $i += $bytesToRead;
        }
    }
    
    //Begin the file stream.
    public function Begin()
    {
        $this->Open();
        $this->SetHeaders();
        $this->Stream();
        $this->End();
    }
}