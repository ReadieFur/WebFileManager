<?php
require_once __DIR__ . '/../../../_assets/configuration/config.php';
require_once __DIR__ . '/../../../_assets/logger/logger.php';
require_once __DIR__ . '/../../../_assets/logger/logLevel.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_shares/webfilemanager_shares.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_google_shares/webfilemanager_google_shares.php';

class ShareHelper
{
    private webfilemanager_paths $pathsTable;
    private webfilemanager_shares $sharesTable;
    private webfilemanager_google_shares $googleSharesTable;

    function __construct()
    {
        $this->pathsTable = new webfilemanager_paths(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );

        $this->sharesTable = new webfilemanager_shares(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );

        $this->googleSharesTable = new webfilemanager_google_shares(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
    }

    public static function VerifyPathAlias($path): bool
    {
        return gettype($path) === 'string' &&
            preg_match_all('/^[a-zA-Z0-9_\-]+$/', $path, $matches) === 1;
    }

    public static function VerifyGoogleShareUsers($users): bool
    {
        if (gettype($users) !== 'array') { return false; }
        for ($i = 0; $i < count($users); $i++)
        {
            $user = $users[$i];
            if (gettype($user) !== 'string') { return false; }
            
            if (str_ends_with($user, '@gmail.com')) { $user = substr($user, 0, strlen($user) - 10); }
            if (preg_match_all('/[A-z\d_\.-]{6,30}/', $user, $matches) !== 1 || $matches[0][0] !== $user) { return false; }
        }
        return true;
    }

    public static function VerifyLocalPath($path, bool $isDirectory): bool
    {
        return gettype($path) === 'string' &&
            preg_match_all('/^\/[a-zA-Z0-9_\-\/].+$/', $path, $matches) === 1 &&
            file_exists($path) &&
            !str_ends_with($path, '/') &&
            ($isDirectory ? is_dir($path) : is_file($path));
    }

    public function AddShare($uid, $path, $shareType, $expiryTime, $googleShareUsers, bool $isDirectory): int | string
    {
        $explodedPath = array_values(
            array_filter(
                explode('/', $path),
                fn($part) => !ctype_space($part) && $part !== ''
            )
        );
        if (count($explodedPath) < 2)
        { return 400; }

        $existingPathResult = $this->pathsTable->Select(array(
            'web_path' => $explodedPath[0]
        ));
        if (empty($existingPathResult))
        { return 404; }

        $locationPart = implode('/', array_slice($explodedPath, 1));
        $localPath = $existingPathResult[0]->local_path . '/' . $locationPart;
        if (!ShareHelper::VerifyLocalPath($localPath, $isDirectory))
        { return 400; }

        $existingShareResult = $this->sharesTable->Select(array(
            'pid' => $existingPathResult[0]->id,
            'AND',
            'path' => $locationPart
        ));
        if (count($existingShareResult) > 0)
        { return 409; }
    
        $id = '';
        do
        {
            $id = str_replace('.', '', uniqid('', true));
            $existingIDs = $this->sharesTable->Select(array('id'=>$id));
            if ($existingIDs === false)
            {
                Logger::Log(
                    array(
                        $this->sharesTable->GetLastException(),
                        $this->sharesTable->GetLastSQLError()
                    ),
                    LogLevel::ERROR
                );
                return 500;
            }
        }
        while (count($existingIDs) > 0);
    
        $insertResult = $this->sharesTable->Insert(array(
            'id' => $id,
            'uid' => $uid,
            'pid' => $existingPathResult[0]->id,
            'path' => $locationPart,
            'share_type' => $shareType,
            'expiry_time' => $expiryTime
        ));
        if ($insertResult === false)
        {
            Logger::Log(
                array(
                    $this->sharesTable->GetLastException(),
                    $this->sharesTable->GetLastSQLError()
                ),
                LogLevel::ERROR
            );
            return 500;
        }

        if ($shareType == EShareType::GOOGLE_INVITE)
        {
            if (!ShareHelper::VerifyGoogleShareUsers($googleShareUsers))
            { return 400; }

            $gshareDeleteResult = $this->googleSharesTable->Delete(array('sid' => $id));
            if ($gshareDeleteResult === false)
            {
                Logger::Log(
                    array(
                        $this->googleSharesTable->GetLastException(),
                        $this->googleSharesTable->GetLastSQLError()
                    ),
                    LogLevel::ERROR
                );
                return 500;
            }

            foreach ($googleShareUsers as $user)
            {
                $gshareID = '';
                do
                {
                    $gshareID = str_replace('.', '', uniqid('', true));
                    $existingIDs = $this->googleSharesTable->Select(array('id'=>$gshareID));
                    if ($existingIDs === false)
                    {
                        Logger::Log(
                            array(
                                $this->googleSharesTable->GetLastException(),
                                $this->googleSharesTable->GetLastSQLError()
                            ),
                            LogLevel::ERROR
                        );
                        return 500;
                    }
                }
                while (count($existingIDs) > 0);

                $gshareInsertResult = $this->googleSharesTable->Insert(array(
                    'id' => $gshareID,
                    'sid' => $id,
                    'user' => $user
                ));
                if ($gshareInsertResult === false)
                {
                    Logger::Log(
                        array(
                            $this->googleSharesTable->GetLastException(),
                            $this->googleSharesTable->GetLastSQLError()
                        ),
                        LogLevel::ERROR
                    );
                    return 500;
                }
            }
        }
    
        return $id;
    }

    public function UpdateShare($uid, $sid, $path, $shareType, $expiryTime, $googleShareUsers, bool $isDirectory): int
    {
        $existingShareResult = $this->sharesTable->Select(array(
            'id' => $sid
        ));
        if (empty($existingShareResult))
        { return 404; }
        else if ($existingShareResult[0]->uid !== $uid)
        { return 403; }
    
        $explodedPath = array_values(
            array_filter(
                explode('/', $path),
                fn($part) => !ctype_space($part) && $part !== ''
            )
        );
        if (count($explodedPath) < 2)
        { return 400; }
    
        $existingPathResult = $this->pathsTable->Select(array(
            'web_path' => $explodedPath[0]
        ));
        if (empty($existingPathResult))
        { return 404; }
    
        $locationPart = implode('/', array_slice($explodedPath, 1));
        $localPath = $existingPathResult[0]->local_path . '/' . $locationPart;
        if (!ShareHelper::VerifyLocalPath($localPath, $isDirectory))
        { return 400; }
    
        $updateResult = $this->sharesTable->Update(array(
            'pid' => $existingPathResult[0]->id,
            'path' => $locationPart,
            'share_type' => $shareType,
            'expiry_time' => $expiryTime
        ), array(
            'id' => $sid
        ));
        if ($updateResult === false)
        {
            Logger::Log(
                array(
                    $this->sharesTable->GetLastException(),
                    $this->sharesTable->GetLastSQLError()
                ),
                LogLevel::ERROR
            );
            return 500;
        }

        $gshareDeleteResult = $this->googleSharesTable->Delete(array('sid' => $sid));
        if ($gshareDeleteResult === false)
        {
            Logger::Log(
                array(
                    $this->googleSharesTable->GetLastException(),
                    $this->googleSharesTable->GetLastSQLError()
                ),
                LogLevel::ERROR
            );
            return 500;
        }

        if ($shareType == EShareType::GOOGLE_INVITE)
        {
            if (!ShareHelper::VerifyGoogleShareUsers($googleShareUsers))
            { return 400; }

            foreach ($googleShareUsers as $user)
            {
                $gshareID = '';
                do
                {
                    $gshareID = str_replace('.', '', uniqid('', true));
                    $existingIDs = $this->googleSharesTable->Select(array('id'=>$gshareID));
                    if ($existingIDs === false)
                    {
                        Logger::Log(
                            array(
                                $this->googleSharesTable->GetLastException(),
                                $this->googleSharesTable->GetLastSQLError()
                            ),
                            LogLevel::ERROR
                        );
                        return 500;
                    }
                }
                while (count($existingIDs) > 0);

                $gshareInsertResult = $this->googleSharesTable->Insert(array(
                    'id' => $gshareID,
                    'sid' => $sid,
                    'user' => $user
                ));
                if ($gshareInsertResult === false)
                {
                    Logger::Log(
                        array(
                            $this->googleSharesTable->GetLastException(),
                            $this->googleSharesTable->GetLastSQLError()
                        ),
                        LogLevel::ERROR
                    );
                    return 500;
                }
            }
        }
    
        return 200;
    }

    public function GetShare($path, $isDirectory): int | object
    {
        $explodedPath = array_values(
            array_filter(
                explode('/', $path),
                fn($part) => !ctype_space($part) && $part !== ''
            )
        );
        if (count($explodedPath) < 2)
        { return 400; }

        $existingPathResult = $this->pathsTable->Select(array(
            'web_path' => $explodedPath[0]
        ));
        if (empty($existingPathResult))
        { return 404; }

        $locationPart = implode('/', array_slice($explodedPath, 1));
        $localPath = $existingPathResult[0]->local_path . '/' . $locationPart;
        if (!ShareHelper::VerifyLocalPath($localPath, $isDirectory))
        { return 400; }

        $existingShareResult = $this->sharesTable->Select(array(
            'pid' => $existingPathResult[0]->id,
            'AND',
            'path' => $locationPart
        ));
        if ($existingShareResult === false) { return 500; }
        else if (empty($existingShareResult))
        {
            $result = new stdClass();
            $result->shared = false;
            return $result;
        }

        $result = new stdClass();
        $result->shared = true;
        $result->sid = $existingShareResult[0]->id;
        $result->share_type = $existingShareResult[0]->share_type;
        $result->expiry_time = $existingShareResult[0]->expiry_time;
        $result->google_share_users = array();
        
        if ($existingShareResult[0]->share_type == EShareType::GOOGLE_INVITE)
        {
            $googleShareUsersResult = $this->googleSharesTable->Select(array(
                'sid' => $existingShareResult[0]->id
            ));
            if ($googleShareUsersResult === false) { return 500; }
            
            foreach ($googleShareUsersResult as $googleShareUser)
            {
                $result->google_share_users[] = $googleShareUser->user;
            }
        }

        return $result;
    }

    public function GetShareByID($sid): int | object
    {
        $existingShareResult = $this->sharesTable->Select(array(
            'id' => $sid
        ));
        if ($existingShareResult === false) { return 500; }
        else if (empty($existingShareResult))
        { return 404; }

        $result = new stdClass();
        $result->shared = true;
        $result->sid = $existingShareResult[0]->id;
        $result->share_type = $existingShareResult[0]->share_type;
        $result->expiry_time = $existingShareResult[0]->expiry_time;
        $result->google_share_users = array();
        
        if ($existingShareResult[0]->share_type == EShareType::GOOGLE_INVITE)
        {
            $googleShareUsersResult = $this->googleSharesTable->Select(array(
                'sid' => $existingShareResult[0]->id
            ));
            if ($googleShareUsersResult === false) { return 500; }
            
            foreach ($googleShareUsersResult as $googleShareUser)
            {
                $result->google_share_users[] = $googleShareUser->user;
            }
        }

        return $result;
    }

    public function DeleteShare($uid, $sid): int
    {
        $existingShareResult = $this->sharesTable->Select(array(
            'id' => $sid
        ));
        if (empty($existingShareResult))
        { return 404; }
        else if ($existingShareResult[0]->uid !== $uid)
        { return 403; }
    
        $deleteResult = $this->sharesTable->Delete(array('id' => $sid), true);
        if ($deleteResult === false)
        {
            Logger::Log(
                array(
                    $this->sharesTable->GetLastException(),
                    $this->sharesTable->GetLastSQLError()
                ),
                LogLevel::ERROR
            );
            return 500;
        }
    
        return 200;
    }
}

class EShareType
{
    public const PRIVATE = 0;
    public const PUBLIC = 1;
    public const GOOGLE_INVITE = 2;
}