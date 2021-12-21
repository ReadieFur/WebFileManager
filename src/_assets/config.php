<?php
require_once __DIR__ . '/logLevel.php';
//Make a file to deny access to paths and files.

class Config
{
    #region REQUIRED CONFIGURATION
    public const DB_HOST = 'localhost';
    public const DB_NAME = 'readie_dev';
    public const DB_USER = 'sqluser';
    public const DB_PASSWORD = 'sqluser';

    //These are the directorties that the website will be able to access, You must supply a name for the path, e.g. 'Videos' => '/home/readie/videos'
    public const PATHS = array(
        'readie' => '/home/readie'
    );
    #endregion

    #region OPTIONAL CONFIGURATION
    //This is the path that the website will be accessed from, e.g. http://localhost/<THIS_PART_OF_THE_URL>
    public const SITE_PATH = '/files';
    //This is the name that will appear in the browser's title bar.
    public const SITE_NAME = '';

    public const LOG_FILE = '/var/log/dedi-readie.global-gaming.co/files.log';
    public const LOG_LEVEL = LogLevel::DEBUG;
    #endregion

    #region STATIC CONFIGURATION - DO NOT MODIFY
    #endregion

    private static function Log(string $message)
    {
        if (!ctype_space(self::LOG_FILE) || file_exists(self::LOG_FILE))
        {
            $logMessage = "[" . LogLevel::GetName(LogLevel::ERROR) . " @ " . date("Y-m-d H:i:s") . "] | [config.php] [string] " . $message . PHP_EOL;
            file_put_contents(self::LOG_FILE, $logMessage, FILE_APPEND);
        }
    }

    public static function CheckConfig()
    {
        if (
            ctype_space(self::DB_HOST) ||
            ctype_space(self::DB_NAME) ||
            ctype_space(self::DB_USER) ||
            ctype_space(self::DB_PASSWORD)
        )
        {
            self::Log('One or more of the required configuration variables are empty.');
            throw new Exception('Missing configuration variables.', 1);
        }
        else if (!empty(array_filter(self::PATHS, function($value, $key)
        {
            return strpos($key, '/') !== false ||
            ctype_space($key) ||
            !is_dir($value);
        }, ARRAY_FILTER_USE_BOTH)))
        {
            self::Log('One or more of the paths are invalid.');
            throw new Exception('Invalid paths.', 1);
        }
    }
}
//Check that the necessary configuration variables are set.
Config::CheckConfig();