<?php
//Check if domain extension is localhost.
$httpHostParts = explode('.', $_SERVER['HTTP_HOST']);
if ($httpHostParts[count($httpHostParts) - 1] !== 'localhost')
{ return http_response_code(403); }

require_once __DIR__ . '/api/v1/request.php';
Request::SendResponse(200);