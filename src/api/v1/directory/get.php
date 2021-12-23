<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../../../_assets/configuration/config.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::GET);
Request::DenyIfDirectRequest(__FILE__);
#endregion

#region URL checks
$path = array_slice(Request::URLStrippedRoot(), 3);
if (!empty($path) && !array_key_exists($path[0], Config::PATHS)) { Request::SendError(400, ErrorMessages::INVALID_PATH); }
#endregion

$response = new stdClass();
$response->path = $path;

#region Path checks
//Check if the request is for the root directory, if so list all avaliable paths.
if (empty($path))
{
    // Logger::Log('Request for configured paths.', LogLevel::DEBUG);
    $response->directories = array_keys(Config::PATHS);
    $response->files = array();
    Request::SendResponse(200, $response);
}

//The request was not for the root directory, so check if the path exists.
$rootDir = Config::PATHS[$path[0]];
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