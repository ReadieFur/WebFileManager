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

function GetDirectory(array $webPath, array $roots, array $path, bool $sharedPath): never
{
    $response = new stdClass();
    $response->sharedPath = $sharedPath;
    $response->path = $webPath;

    #region Path checks
    //Check if the request is for the root directory, if so list all avaliable paths.
    if (empty($webPath))
    {
        // Logger::Log('Request for configured paths.', LogLevel::DEBUG);
        $response->directories = array_keys($roots);
        $response->files = array();
        Request::SendResponse(200, $response);
    }

    //The request was not for the root directory, so check if the path exists.
    $formattedPath = '/' . implode('/', $path);
    if (is_dir($formattedPath))
    {
        // Logger::Log('Getting contents of directory: ' . $formattedPath, LogLevel::DEBUG);
        $response->directories = array_values(array_filter(scandir($formattedPath), fn($dir) => is_dir($formattedPath . '/' . $dir) && $dir !== '.' && $dir !== '..'));
        $files = array_filter(scandir($formattedPath), fn($file) => !is_dir($formattedPath . '/' . $file));
        $filesWithDetails = array();
        foreach ($files as $file)
        {
            $fullPath = $formattedPath . '/' . $file;
            $pathInfo = pathinfo($fullPath);
            $fileDetails = new stdClass();
            $fileDetails->name = $pathInfo['filename'];
            $fileDetails->extension = $pathInfo['extension'];
            $fileDetails->size = filesize($fullPath);
            $fileDetails->lastModified = filemtime($fullPath);
            $fileDetails->mimeType = mime_content_type($fullPath);

            //To keep directory listings fast I will not send extra information about files. FFMPEG will slow down the request a lot, if more information is needed for that file the client can request it by sending a request for the file with ?details=true.
            // try
            // {
            //     $mimeParentType = explode('/', $fileDetails->mimeType)[0];
            //     if ($mimeParentType === 'image')
            //     {
            //         list($width, $height) = getimagesize($fullPath);
            //         $fileDetails->width = $width;
            //         $fileDetails->height = $height;
            //     }
            //     else if ($mimeParentType === 'video')
            //     {
            //         $ffprobe = FFMpeg\FFProbe::create(array(
            //             'ffmpeg.binaries' => Config::Config()['ffmpeg']['binaries']['ffmpeg'],
            //             'ffprobe.binaries' => Config::Config()['ffmpeg']['binaries']['ffprobe']
            //         ));
            //         $stream = $ffprobe->streams($fullPath)->videos()->first();
            //         $fileDetails->width = intval($stream->get('width'));
            //         $fileDetails->height = intval($stream->get('height'));
            //     }
            // }
            // catch (Exception $e)
            // {
            //     Logger::Log('Failed to get file details: ' . $e->getMessage(), LogLevel::ERROR);
            //     Request::SendError(500, ErrorMessages::UNKNOWN_ERROR);
            // }

            $filesWithDetails[] = $fileDetails;
        }
        $response->files = $filesWithDetails;
        Request::SendResponse(200, $response);
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
    Request::SendError(500, ErrorMessages::UNKNOWN_ERROR);
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
            !isset(Request::Get()['uid']) ||
            !isset(Request::Get()['token'])
        )
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        $accountHelper = new AccountHelper();
        $accountResult = $accountHelper->VerifyToken(
            Request::Get()['uid'],
            Request::Get()['token']
        );
        if ($accountResult === false)
        { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

        $searchPath = array_merge(
            array_filter(explode('/', $roots[$path[0]]), fn($part) => !ctype_space($part) && $part !== ''),
            array_filter(array_slice($path, 1), fn($part) => !ctype_space($part) && $part !== '')
        );
        GetDirectory($path, $roots, $searchPath, false);
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
            Request::SendError(500, ErrorMessages::UNKNOWN_ERROR);
        }
        else if (empty($sharesResponse))
        { Request::SendError(404, ErrorMessages::INVALID_PATH); }

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
                GetDirectory($path, $roots, $searchPath, true);
            case 1:
                //Public with timeout.
                if (time() > $share->expiry_time)
                { Request::SendError(403, ErrorMessages::SHARE_EXPIRED); }
                GetDirectory($path, $roots, $searchPath, true);
            default:
                Request::SendError(500, ErrorMessages::UNKNOWN_ERROR);
        }
    }
}
else
{
    if (
        !isset(Request::Get()['uid']) ||
        !isset(Request::Get()['token'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Get()['uid'],
        Request::Get()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    GetDirectory(array(), $roots, array(), false);
}