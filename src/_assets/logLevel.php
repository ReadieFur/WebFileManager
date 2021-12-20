<?php
class LogLevel
{
    public const DEBUG = 0;
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    public static function GetName(int $level): string
    {
        switch ($level)
        {
            case self::DEBUG:
                return 'DEBUG';
            case self::INFO:
                return 'INFO';
            case self::WARNING:
                return 'WARNING';
            case self::ERROR:
                return 'ERROR';
            default:
                return 'UNKNOWN';
        }
    }
}