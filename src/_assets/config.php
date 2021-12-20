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

    //This is the path that the website will be accessed from, e.g. http://localhost/<THIS_PART_OF_THE_URL>
    public const SITE_PATH = '/files';

    //These are the directorties that the website will be able to access, You must supply a name for the path, e.g. 'Videos' => '/home/readie/videos'
    public const PATHS = array(
        'TestDrive' => '/home/readie'
    );
    #endregion

    #region OPTIONAL CONFIGURATION
    //This is the name that will appear in the browser's title bar.
    public const SITE_NAME = '';

    public const LOG_FILE = '/var/log/dedi-readie.global-gaming.co/files.log';
    public const LOG_LEVEL = LogLevel::DEBUG;
    #endregion

    #region STATIC CONFIGURATION - DO NOT MODIFY
    public const ROOT_DIR = __DIR__;
    #endregion
}

//Check that the necessary configuration variables are set.
if (
    ctype_space(Config::DB_HOST) ||
    ctype_space(Config::DB_NAME) ||
    ctype_space(Config::DB_USER) ||
    ctype_space(Config::DB_PASSWORD) ||
    ctype_space(Config::SITE_PATH)
)
{
    throw new Exception('Missing configuration variables.', 1);
}