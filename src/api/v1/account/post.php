<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/accountHelper.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::POST);
Request::DenyIfDirectRequest(__FILE__);
#endregion

#region Parameter checks
if (!isset(Request::Post()['method']))
{ Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }
#endregion

function CreateAccount(): never
{
    if (
        !isset(Request::Post()['username']) ||
        !isset(Request::Post()['password'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->CreateAccount(
        Request::Post()['username'],
        Request::Post()['password']
    );
    //While it isn't good to assume why the account creation failed, it's most likley due to the data not matching or an account already existing, so return a 409.
    //And yes I could easily add a function in the AccountHelper to return why the account creation failed, but I can't be arsed.
    //In the future, maybe API V2, I will return 200 with most requests and then return the reason why an error occured, like in my current main API.
    if ($accountResult === false)
    { Request::SendError(409, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $response = new stdClass();
    $response->id = $accountResult;
    Request::SendResponse(200, $response);
}

function DeleteAccount(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }
    
    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->DeleteAccount(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    Request::SendResponse(200);
}

function LogIn(): never
{
    if (
        !isset(Request::Post()['username']) ||
        !isset(Request::Post()['password'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->LogIn(
        Request::Post()['username'],
        Request::Post()['password']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $response = new stdClass();
    $response->token = $accountResult;
    Request::SendResponse(200, $response);
}

function LogOut(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->LogOut(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    Request::SendResponse(200);
}

function VerifyToken(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    Request::SendResponse(200);
}

switch (Request::Post()['method'])
{
    case 'create_account':
        CreateAccount();
    case 'delete_account':
        DeleteAccount();
    case 'log_in':
        LogIn();
    case 'log_out':
        LogOut();
    case 'verify_token':
        VerifyToken();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}