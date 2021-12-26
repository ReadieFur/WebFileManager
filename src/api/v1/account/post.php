<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../_helpers/accountHelper.php';

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
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['username']) ||
        !isset(Request::Post()['password']) ||
        !isset(Request::Post()['admin'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->CreateAccount(
        Request::Post()['id'],
        Request::Post()['token'],
        Request::Post()['username'],
        Request::Post()['password'],
        Request::Post()['admin']
    );
    //While it isn't good to assume why the account creation failed, it's most likley due to the data not matching or an account already existing, so return a 409.
    //And yes I could easily add a function in the AccountHelper to return why the account creation failed, but I can't be arsed.
    //In the future, maybe API V2, I will return 200 with most requests and then return the reason why an error occured, like in my current main API.
    if ($accountResult === false)
    { Request::SendError(409, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $response = new stdClass();
    $response->uid = $accountResult;
    Request::SendResponse(200, $response);
}

function UpdateAccount(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['uid']) ||
        // !isset(Request::Post()['password']) ||
        !isset(Request::Post()['admin'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->UpdateAccount(
        Request::Post()['id'],
        Request::Post()['token'],
        Request::Post()['uid'],
        Request::Post()['old_password']??null,
        Request::Post()['new_password']??null,
        Request::Post()['admin']
    );
    if ($accountResult === false)
    { Request::SendError(409, ErrorMessages::INVALID_ACCOUNT_DATA); }

    Request::SendResponse(200);
}

function DeleteAccount(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['uid'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }
    
    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->DeleteAccount(
        Request::Post()['id'],
        Request::Post()['token'],
        Request::Post()['uid']
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

    Request::SendResponse(200, $accountResult);
}

function RevokeSession(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['uid'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->RevokeSession(
        Request::Post()['id'],
        Request::Post()['token'],
        Request::Post()['uid']
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

function GetAccountDetails(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['uid'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->GetAccountDetails(
        Request::Post()['id'],
        Request::Post()['token'],
        Request::Post()['uid']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $filteredResult = new stdClass();
    $filteredResult->username = $accountResult->username;
    $filteredResult->admin = $accountResult->admin;
    Request::SendResponse(200, $filteredResult);
}

switch (Request::Post()['method'])
{
    case 'create_account':
        CreateAccount();
    case 'update_account':
        UpdateAccount();
    case 'delete_account':
        DeleteAccount();
    case 'log_in':
        LogIn();
    case 'revoke_session':
        RevokeSession();
    case 'verify_token':
        VerifyToken();
    case 'get_account_details':
        GetAccountDetails();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}