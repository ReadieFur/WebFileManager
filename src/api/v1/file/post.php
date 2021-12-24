<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../_helpers/accountHelper.php';
require_once __DIR__ . '/../_helpers/shareHelper.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_shares/webfilemanager_shares.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::POST);
Request::DenyIfDirectRequest(__FILE__);
#endregion

#region Parameter checks
if (!isset(Request::Post()['method']))
{ Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }
#endregion

function AddShare(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['path']) ||
        !isset(Request::Post()['share_type']) ||
        !isset(Request::Post()['expiry_time'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $shareHelper = new ShareHelper();
    $result = $shareHelper->AddShare(
        Request::Post()['id'],
        Request::Post()['path'],
        Request::Post()['share_type'],
        Request::Post()['expiry_time'],
        false
    );
    
    if (is_int($result))
    {
        switch ($result)
        {
            case 400:
                Request::SendError($result, ErrorMessages::INVALID_PARAMETERS);
            case 404:
                Request::SendError($result, ErrorMessages::INVALID_PATH);
            case 409:
                Request::SendError($result, ErrorMessages::PATH_ALREADY_EXISTS);
            case 500:
                Request::SendError($result, ErrorMessages::DATABASE_ERROR);
            default:
                Request::SendError($result, ErrorMessages::UNKNOWN_ERROR);
        }
    }

    $response = new stdClass();
    $response->sid = $result;
    Request::SendResponse(200, $response);
}

function UpdateShare(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['sid']) ||
        !isset(Request::Post()['path']) ||
        !isset(Request::Post()['share_type']) || Request::Post()['share_type'] > 1 || Request::Post()['share_type'] < 0 ||
        !isset(Request::Post()['expiry_time']) || !ctype_digit(Request::Post()['expiry_time'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $shareHelper = new ShareHelper();
    $result = $shareHelper->UpdateShare(
        Request::Post()['id'],
        Request::Post()['sid'],
        Request::Post()['path'],
        Request::Post()['share_type'],
        Request::Post()['expiry_time'],
        false
    );

    switch ($result)
    {
        case 200:
            Request::SendResponse($result);
        case 400:
            Request::SendError($result, ErrorMessages::INVALID_PARAMETERS);
        case 404:
            Request::SendError($result, ErrorMessages::INVALID_PATH);
        case 409:
            Request::SendError($result, ErrorMessages::PATH_ALREADY_EXISTS);
        case 500:
            Request::SendError($result, ErrorMessages::DATABASE_ERROR);
        default:
            Request::SendError($result, ErrorMessages::UNKNOWN_ERROR);
    }
}

function DeleteShare(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['sid'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $shareHelper = new ShareHelper();
    $result = $shareHelper->DeleteShare(
        Request::Post()['id'],
        Request::Post()['sid'],
        false
    );

    switch ($result)
    {
        case 200:
            Request::SendResponse($result);
        case 400:
            Request::SendError($result, ErrorMessages::INVALID_PARAMETERS);
        case 403:
            Request::SendError($result, ErrorMessages::INVALID_ACCOUNT_DATA);
        case 404:
            Request::SendError($result, ErrorMessages::INVALID_PATH);
        case 500:
            Request::SendError($result, ErrorMessages::DATABASE_ERROR);
        default:
            Request::SendError($result, ErrorMessages::UNKNOWN_ERROR);
    }
}

switch (Request::Post()['method'])
{
    case 'add_share':
        AddShare();
    case 'update_share':
        UpdateShare();
    case 'delete_share':
        DeleteShare();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}