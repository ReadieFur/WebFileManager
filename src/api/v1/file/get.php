<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../../../_assets/configuration/config.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::GET);
Request::DenyIfDirectRequest(__FILE__);
#endregion

#region URL checks
$path = array_slice(Request::URLStrippedRoot(), 3);
if (empty($path) || !array_key_exists($path[0], Config::PATHS)) { Request::SendError(400, ErrorMessages::INVALID_PATH); }
#endregion

//https://github.com/kOFReadie/Cloud/blob/main/src/files/storage/index.php
#region Path checks
$rootDir = Config::PATHS[$path[0]];
$strippedPath = array_slice($path, 1);
$formattedPath = $rootDir . '/' . implode('/', $strippedPath);
if (is_file($formattedPath))
{
    // Logger::Log('Getting contents of file: ' . $formattedPath, LogLevel::DEBUG);
    $fileStream = new FileStream($formattedPath, array_key_exists('download', Request::Get()));
    $fileStream->Begin();
    // exit; //This is called from the end function which is called from the begin function above.
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