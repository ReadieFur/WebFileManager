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

    if (gettype($accountResult) === 'integer')
    {
        switch ($accountResult)
        {
            case 400:
                Request::SendError($accountResult, ErrorMessages::INVALID_PARAMETERS);
            case 403:
                Request::SendError($accountResult, ErrorMessages::INVALID_ACCOUNT_DATA);
            case 409:
                Request::SendError($accountResult, ErrorMessages::ACCOUNT_ALREADY_EXISTS);
            case 500:
                Request::SendError($accountResult, ErrorMessages::UNKNOWN_ERROR);
            default:
                Request::SendError($accountResult, ErrorMessages::UNKNOWN_ERROR);
        }
    }

    $response = new stdClass();
    $response->uid = $accountResult;
    Request::SendResponse(200, $response);
}

function UpdateAccount(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['uid'])
        // !isset(Request::Post()['password']) ||
        // !isset(Request::Post()['admin'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->UpdateAccount(
        Request::Post()['id'],
        Request::Post()['token'],
        Request::Post()['uid'],
        Request::Post()['old_password']??null,
        Request::Post()['new_password']??null,
        Request::Post()['admin']??null
    );
    
    switch ($accountResult)
    {
        case 200:
            Request::SendResponse(200);
        case 400:
            Request::SendError($accountResult, ErrorMessages::INVALID_PARAMETERS);
        case 403:
            Request::SendError($accountResult, ErrorMessages::INVALID_ACCOUNT_DATA);
        case 404:
            Request::SendError($accountResult, ErrorMessages::ACCOUNT_NOT_FOUND);
        case 500:
            Request::SendError($accountResult, ErrorMessages::UNKNOWN_ERROR);
        default:
            Request::SendError($accountResult, ErrorMessages::UNKNOWN_ERROR);
    }
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

    switch ($accountResult)
    {
        case 200:
            Request::SendResponse(200);
        case 400:
            Request::SendError($accountResult, ErrorMessages::INVALID_PARAMETERS);
        case 403:
            Request::SendError($accountResult, ErrorMessages::INVALID_ACCOUNT_DATA);
        case 404:
            Request::SendError($accountResult, ErrorMessages::ACCOUNT_NOT_FOUND);
        case 500:
            Request::SendError($accountResult, ErrorMessages::UNKNOWN_ERROR);
        default:
            Request::SendError($accountResult, ErrorMessages::UNKNOWN_ERROR);
    }
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
    $filteredResult->uid = $accountResult->id;
    $filteredResult->username = $accountResult->username;
    $filteredResult->admin = $accountResult->admin;
    Request::SendResponse(200, $filteredResult);
}

function GetAllAccounts(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountsResult = $accountHelper->GetAllAccounts(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountsResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $filteredResult = new stdClass();
    $filteredResult->accounts = array();
    foreach ($accountsResult as $user)
    {
        $filteredResult->accounts[] = array(
            'uid' => $user->id,
            'username' => $user->username,
            'admin' => $user->admin == 1 ? 1 : 0
        );
    }
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
    case 'get_all_accounts':
        GetAllAccounts();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}