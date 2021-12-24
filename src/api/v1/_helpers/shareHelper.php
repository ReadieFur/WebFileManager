<?php
require_once __DIR__ . '/../../../_assets/configuration/config.php';
require_once __DIR__ . '/../../../_assets/logger/logger.php';
require_once __DIR__ . '/../../../_assets/logger/logLevel.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_paths/webfilemanager_paths.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_shares/webfilemanager_shares.php';

class ShareHelper
{
    private webfilemanager_paths $pathsTable;
    private webfilemanager_shares $sharesTable;

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
    }

    public static function VerifyPathAlias($path): bool
    {
        return gettype($path) === 'string' &&
            preg_match_all('/^[a-zA-Z0-9_\-]+$/', $path, $matches) === 1;
    }

    public static function VerifyLocalPath($path, bool $isDirectory): bool
    {
        return gettype($path) === 'string' &&
            preg_match_all('/^\/[a-zA-Z0-9_\-\/].+$/', $path, $matches) === 1 &&
            file_exists($path) &&
            !str_ends_with($path, '/') &&
            ($isDirectory ? is_dir($path) : is_file($path));
    }

    public function AddShare($uid, $path, $shareType, $expiryTime, bool $isDirectory): int | string
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
    
        return $id;
    }

    public function UpdateShare($uid, $sid, $path, $shareType, $expiryTime, bool $isDirectory): int
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
    
        return 200;
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
    
        $deleteResult = $this->sharesTable->Delete(array(
            'id' => $sid
        ));
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