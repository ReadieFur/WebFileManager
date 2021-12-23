<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../../../_assets/configuration/config.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::GET);
Request::DenyIfDirectRequest(__FILE__);
#endregion

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
    Request::SendError(500, 'Failed to get root paths');
}
$paths = array();
foreach ($pathsResponse as $path)
{
    $paths[$path->web_path] = $path->local_path;
}
#endregion

#region URL checks
$path = array_slice(Request::URLStrippedRoot(), 3);
if (!empty($path) && !array_key_exists($path[0], $paths)) { Request::SendError(400, ErrorMessages::INVALID_PATH); }
#endregion

$response = new stdClass();
$response->path = $path;

#region Path checks
//Check if the request is for the root directory, if so list all avaliable paths.
if (empty($path))
{
    // Logger::Log('Request for configured paths.', LogLevel::DEBUG);
    $response->directories = array_keys($paths);
    $response->files = array();
    Request::SendResponse(200, $response);
}

//The request was not for the root directory, so check if the path exists.
$rootDir = $paths[$path[0]];
$strippedPath = array_slice($path, 1);
$formattedPath = $rootDir . '/' . implode('/', $strippedPath);
if (is_dir($formattedPath))
{
    // Logger::Log('Getting contents of directory: ' . $formattedPath, LogLevel::DEBUG);
    $response->directories = array_filter(scandir($formattedPath), fn($dir) => is_dir($formattedPath . '/' . $dir) && $dir !== '.' && $dir !== '..');
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
        $filesWithDetails[] = $fileDetails;
    }
    $response->files = $filesWithDetails;
    Request::SendResponse(200, $response);
}
else
{
    Request::SendError(400, ErrorMessages::INVALID_PATH);
}