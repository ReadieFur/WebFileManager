<?php
require_once __DIR__ . '/../request.php';

switch (Request::RequestMethod())
{
    case RequestMethod::GET:
        require_once __DIR__ . '/get.php';
        break;
    case RequestMethod::POST:
        require_once __DIR__ . '/post.php';
        break;
    default:
        Request::SendResponse(405); //405 Method Not Allowed.
        //Break is not needed here as SendResponse will never return.
}
//If this point is reached (which it shouldn't) the program will return error 500.
Request::SendResponse(500); //500 Internal Server Error.