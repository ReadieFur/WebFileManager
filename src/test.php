<?php
//Check if domain extension is localhost.
$httpHostParts = explode('.', $_SERVER['HTTP_HOST']);
if ($httpHostParts[count($httpHostParts) - 1] !== 'localhost')
{ return http_response_code(403); }

yaml_parse_file('_assets/config.yaml');