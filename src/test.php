<?php
//Check if domain extension is localhost.
$httpHostParts = explode('.', $_SERVER['HTTP_HOST']);
if ($httpHostParts[count($httpHostParts) - 1] !== 'localhost')
{ return http_response_code(403); }
