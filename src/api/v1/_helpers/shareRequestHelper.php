<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/accountHelper.php';
require_once __DIR__ . '/shareHelper.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_shares/webfilemanager_shares.php';

class ShareRequestHelper
{
    public static function AddRoot(bool $isDirectory): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token']) ||
            !isset(Request::Post()['web_path']) ||
            !isset(Request::Post()['local_path'])
        )
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        if (!ShareHelper::VerifyPathAlias(Request::Post()['web_path']) || !ShareHelper::VerifyLocalPath(Request::Post()['local_path'], $isDirectory))
        { Request::SendError(400, ErrorMessages::INVALID_PATH); }

        $accountHelper = new AccountHelper();
        $accountResult = $accountHelper->GetAccountDetails(
            Request::Post()['id'],
            Request::Post()['token'],
            Request::Post()['id']
        );
        if ($accountResult === false || $accountResult->admin != 1)
        { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

        $pathsTable = new webfilemanager_paths(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
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

    public static function UpdateRoot(bool $isDirectory): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token']) ||
            !isset(Request::Post()['old_web_path']) ||
            !isset(Request::Post()['new_web_path']) ||
            !isset(Request::Post()['new_local_path'])
        )
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        if (!ShareHelper::VerifyPathAlias(Request::Post()['old_web_path']) || !ShareHelper::VerifyPathAlias(Request::Post()['new_web_path']) || !ShareHelper::VerifyLocalPath(Request::Post()['new_local_path'], $isDirectory))
        { Request::SendError(400, ErrorMessages::INVALID_PATH); }

        $accountHelper = new AccountHelper();
        $accountResult = $accountHelper->GetAccountDetails(
            Request::Post()['id'],
            Request::Post()['token'],
            Request::Post()['id']
        );
        if ($accountResult === false || $accountResult->admin != 1)
        { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

        $pathsTable = new webfilemanager_paths(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
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

    public static function GetRoots(): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token'])
        )
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        $accountHelper = new AccountHelper();
        $accountResult = $accountHelper->GetAccountDetails(
            Request::Post()['id'],
            Request::Post()['token'],
            Request::Post()['id']
        );
        if ($accountResult === false || $accountResult->admin != 1)
        { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

        $pathsTable = new webfilemanager_paths(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
        $pathsResult = $pathsTable->Select(array());
        if ($pathsResult === false)
        { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

        $response = new stdClass();
        $response->paths = array();
        foreach ($pathsResult as $path)
        {
            $response->paths[] = array(
                'web_path' => $path->web_path,
                'local_path' => $path->local_path
            );
        }
        
        Request::SendResponse(200, $response);
    }

    public static function DeleteRoot(): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token']) ||
            !isset(Request::Post()['web_path'])
        )
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        $accountHelper = new AccountHelper();
        $accountResult = $accountHelper->GetAccountDetails(
            Request::Post()['id'],
            Request::Post()['token'],
            Request::Post()['id']
        );
        if ($accountResult === false || $accountResult->admin != 1)
        { Request::SendError(401, ErrorMessages::INVALID_ACCOUNT_DATA); }

        $pathsTable = new webfilemanager_paths(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
        $deleteResult = $pathsTable->Delete(array(
            'web_path' => Request::Post()['web_path']
        ));
        if ($deleteResult === false)
        { Request::SendError(500, ErrorMessages::DATABASE_ERROR); }

        Request::SendResponse(200);
    }

    public static function AddShare(bool $isDirectory): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token']) ||
            !isset(Request::Post()['path']) ||
            !isset(Request::Post()['share_type']) || Request::Post()['share_type'] > 2 || Request::Post()['share_type'] < 0 ||
            !isset(Request::Post()['expiry_time']) || (Request::Post()['expiry_time'] != '0' && intval(Request::Post()['expiry_time']) == 0) ||
            !isset(Request::Post()['google_share_users'])
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
            Request::Post()['google_share_users'],
            $isDirectory
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

    public static function UpdateShare(bool $isDirectory): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token']) ||
            !isset(Request::Post()['sid']) ||
            !isset(Request::Post()['path']) ||
            !isset(Request::Post()['share_type']) || Request::Post()['share_type'] > 2 || Request::Post()['share_type'] < 0 ||
            !isset(Request::Post()['expiry_time']) || (Request::Post()['expiry_time'] != '0' && intval(Request::Post()['expiry_time']) == 0) ||
            !isset(Request::Post()['google_share_users'])
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
            Request::Post()['google_share_users'],
            $isDirectory
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

    public static function GetShare(bool $isDirectory): never
    {
        if (
            !isset(Request::Post()['id']) ||
            !isset(Request::Post()['token']) ||
            !isset(Request::Post()['path'])
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
        $result = $shareHelper->GetShare(
            Request::Post()['path'],
            $isDirectory
        );
        
        if (is_int($result))
        {
            switch ($result)
            {
                case 400:
                    Request::SendError($result, ErrorMessages::INVALID_PARAMETERS);
                case 404:
                    Request::SendError($result, ErrorMessages::INVALID_PATH);
                case 500:
                    Request::SendError($result, ErrorMessages::DATABASE_ERROR);
                default:
                    Request::SendError($result, ErrorMessages::UNKNOWN_ERROR);
            }
        }

        Request::SendResponse(200, $result);
    }

    public static function GetShareByID(bool $isDirectory)
    {
        if (!isset(Request::Post()['sid']))
        { Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }

        //This method, while it shares the same data as GetShare, because it is using the share ID, it is not sensitive and therefore I do not need to verify the account.

        $shareHelper = new ShareHelper();
        $result = $shareHelper->GetShareByID(
            Request::Post()['sid'],
            $isDirectory
        );
        
        if (is_int($result))
        {
            switch ($result)
            {
                case 404:
                    Request::SendError($result, ErrorMessages::INVALID_PATH);
                case 500:
                    Request::SendError($result, ErrorMessages::DATABASE_ERROR);
                default:
                    Request::SendError($result, ErrorMessages::UNKNOWN_ERROR);
            }
        }

        Request::SendResponse(200, $result);
    }

    public static function DeleteShare(): never
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
            Request::Post()['sid']
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
}