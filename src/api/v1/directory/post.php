<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../../account/accountHelper.php';
require_once __DIR__ . '/../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';
require_once __DIR__ . '/../../_assets/database/tables/webfilemanager_shares/webfilemanager_shares.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::POST);
Request::DenyIfDirectRequest(__FILE__);
#endregion

#region Parameter checks
if (!isset(Request::Post()['method']))
{ Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }
#endregion

function VerifyPathAlias($path)
{
    return gettype($path) === 'string' &&
        preg_match_all('/^[a-zA-Z0-9_\-]+$/', $path, $matches) === 1;
}

function VerifyLocalPath($path): bool
{
    return gettype($path) === 'string' &&
        preg_match_all('/^\/[a-zA-Z0-9_\-\/]+$/', $path, $matches) === 1 &&
        file_exists($path) &&
        !is_dir($path);
}

function AddRoot(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['web_path']) ||
        !isset(Request::Post()['local_path'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    if (!VerifyPathAlias(Request::Post()['web_path']) || !VerifyLocalPath(Request::Post()['local_path']))
    { Request::SendError(400, ErrorMessages::INVALID_PATH); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $pathsTable = new webfilemanager_paths(true);
    $existingPathResult = $pathsTable->Select(array(
        'web_path' => Request::Post()['web_path']
    ));
    if (count($existingPathResult) > 0)
    { Request::SendError(409, ErrorMessages::PATH_ALREADY_EXISTS); }

    $id = '';
    do
    {
        $id = str_replace('.', '', uniqid('', true));
        $existingIDs = $pathsTable->Select(array('id'=>$id));
        if ($existingIDs === false) { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }
    }
    while (count($existingIDs) > 0);

    $insertResult = $pathsTable->Insert(array(
        'id' => $id,
        'web_path' => Request::Post()['web_path'],
        'local_path' => Request::Post()['local_path']
    ));
    if ($insertResult === false)
    { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

    Request::SendResponse(200);
}

function UpdateRoot(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['old_web_path']) ||
        !isset(Request::Post()['new_web_path']) ||
        !isset(Request::Post()['new_local_path'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    if (!VerifyPathAlias(Request::Post()['old_web_path']) || !VerifyPathAlias(Request::Post()['new_web_path']) || !VerifyLocalPath(Request::Post()['new_local_path']))
    { Request::SendError(400, ErrorMessages::INVALID_PATH); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $pathsTable = new webfilemanager_paths(true);
    $existingPathResult = $pathsTable->Select(array(
        'web_path' => Request::Post()['old_web_path']
    ));
    if (count($existingPathResult) === 0)
    { Request::SendError(404, ErrorMessages::INVALID_PATH); }

    $updateResult = $pathsTable->Update(
        array(
            'web_path' => Request::Post()['new_web_path'],
            'local_path' => Request::Post()['new_local_path']
        ),
        array(
            'web_path' => Request::Post()['old_web_path']
        )
    );
    if ($updateResult === false)
    { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

    Request::SendResponse(200);
}

function DeleteRoot(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['web_path'])
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $pathsTable = new webfilemanager_paths(true);
    $deleteResult = $pathsTable->Delete(array(
        'web_path' => Request::Post()['web_path']
    ));
    if ($deleteResult === false)
    { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

    Request::SendResponse(200);
}

function AddShare(): never
{
    if (
        !isset(Request::Post()['id']) ||
        !isset(Request::Post()['token']) ||
        !isset(Request::Post()['path']) ||
        !isset(Request::Post()['share_type']) || Request::Post()['share_type'] > 1 || Request::Post()['share_type'] < 0 ||
        !isset(Request::Post()['expiry_time']) || !ctype_digit(Request::Post()['expiry_time'])
        // !isset(Request::Post()['expiry_time']) || Request::Post()['expiry_time'] < Time() - 60 // Allow for a minute of clock drift. (I noticed that checking if the time is in the past could result in some issues arising when updating a share, this could be fixed but I won't bother for now).
    )
    { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

    $path = array_filter(explode('/', Request::Post()['path']), fn($part) => !ctype_space($part) && $part !== '');
    if (count($path) < 2)
    { Request::SendError(400, ErrorMessages::INVALID_PATH); }

    $accountHelper = new AccountHelper();
    $accountResult = $accountHelper->VerifyToken(
        Request::Post()['id'],
        Request::Post()['token']
    );
    if ($accountResult === false)
    { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $pathsTable = new webfilemanager_paths(true);
    $existingPathResult = $pathsTable->Select(array(
        'web_path' => $path[0]
    ));
    if (empty($existingPathResult))
    { Request::SendError(404, ErrorMessages::INVALID_PATH); }

    $locationPart = implode('/', array_slice($path, 1));
    $localPath = $existingPathResult[0]['local_path'] . '/' . $locationPart;
    if (!VerifyLocalPath($localPath))
    { Request::SendError(400, ErrorMessages::INVALID_PATH); }

    $sharesTable = new webfilemanager_shares(true);
    $existingShareResult = $sharesTable->Select(array(
        'path' => Request::Post()['path']
    ));
    if (count($existingShareResult) > 0)
    { Request::SendError(409, ErrorMessages::PATH_ALREADY_EXISTS); }

    $id = '';
    do
    {
        $id = str_replace('.', '', uniqid('', true));
        $existingIDs = $sharesTable->Select(array('id'=>$id));
        if ($existingIDs === false) { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }
    }
    while (count($existingIDs) > 0);

    $insertResult = $sharesTable->Insert(array(
        'id' => $id,
        'uid' => Request::Post()['id'],
        'pid' => $existingPathResult[0]['id'],
        'path' => $locationPart,
        'share_type' => Request::Post()['share_type'],
        'expiry_time' => Request::Post()['expiry_time']
    ));
    if ($insertResult === false)
    { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

    $response = new stdClass();
    $response->id = $id;
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

    $sharesTable = new webfilemanager_shares(true);
    $existingShareResult = $sharesTable->Select(array(
        'id' => Request::Post()['sid']
    ));
    if (empty($existingShareResult))
    { Request::SendError(404, ErrorMessages::INVALID_PATH); }
    else if ($existingShareResult[0]['uid'] !== Request::Post()['id'])
    { Request::SendError(403, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $path = array_filter(explode('/', Request::Post()['path']), fn($part) => !ctype_space($part) && $part !== '');
    if (count($path) < 2)
    { Request::SendError(400, ErrorMessages::INVALID_PATH); }

    $pathsTable = new webfilemanager_paths(true);
    $existingPathResult = $pathsTable->Select(array(
        'web_path' => $path[0]
    ));
    if (empty($existingPathResult))
    { Request::SendError(404, ErrorMessages::INVALID_PATH); }

    $locationPart = implode('/', array_slice($path, 1));
    $localPath = $existingPathResult[0]['local_path'] . '/' . $locationPart;
    if (!VerifyLocalPath($localPath))
    { Request::SendError(400, ErrorMessages::INVALID_PATH); }

    $updateResult = $sharesTable->Update(array(
        'pid' => $existingPathResult[0]['id'],
        'path' => $locationPart,
        'share_type' => Request::Post()['share_type'],
        'expiry_time' => Request::Post()['expiry_time']
    ), array(
        'id' => Request::Post()['sid']
    ));
    if ($updateResult === false)
    { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

    Request::SendResponse(200);
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

    $sharesTable = new webfilemanager_shares(true);
    $existingShareResult = $sharesTable->Select(array(
        'id' => Request::Post()['sid']
    ));
    if (empty($existingShareResult))
    { Request::SendError(404, ErrorMessages::INVALID_PATH); }
    else if ($existingShareResult[0]['uid'] !== Request::Post()['id'])
    { Request::SendError(403, ErrorMessages::INVALID_ACCOUNT_DATA); }

    $deleteResult = $sharesTable->Delete(array(
        'id' => Request::Post()['sid']
    ));
    if ($deleteResult === false)
    { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

    Request::SendResponse(200);
}

switch (Request::Post()['method'])
{
    case 'add_root':
        AddRoot();
    case 'update_root':
        UpdateRoot();
    case 'delete_root':
        DeleteRoot();
    case 'add_share':
        AddShare();
    case 'update_share':
        UpdateShare();
    case 'delete_share':
        DeleteShare();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}