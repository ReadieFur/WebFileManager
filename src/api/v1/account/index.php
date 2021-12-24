<?php
require_once __DIR__ . '/../request.php';
//I would like to be able to use the delete method, but it seems that it's not allowed to have a request body.
Request::RunScriptForRequestMethod(__DIR__);