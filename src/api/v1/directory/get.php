<?php
require_once __DIR__ . '/../request.php';
Request::DenyIfNotRequestMethod(RequestMethod::GET);
Request::SendResponse(200);