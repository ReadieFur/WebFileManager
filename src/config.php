<?php
require_once __DIR__ . '/logLevel.php';

class Config
{
    #region REQUIRED CONFIGURATION
    public const DB_HOST = 'localhost';
    public const DB_NAME = '';
    public const DB_USER = '';
    public const DB_PASSWORD = '';

    public const SITE_URL = '';
    #endregion

    #region OPTIONAL CONFIGURATION
    public const SITE_NAME = '';

    public const LOG_FILE = '';
    public const LOG_LEVEL = LogLevel::WARNING;
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
    ctype_space(Config::SITE_URL)
)
{
    throw new Exception('Missing configuration variables.', 1);
}