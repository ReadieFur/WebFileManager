<?php
require_once __DIR__ . '/../configuration/config.php';
require_once __DIR__ . '/logLevel.php';

class Logger
{
    public static function Log($message, int $level): void
    {
        if (
            !isset(Config::Config()['log']) ||
            !isset(Config::Config()['log']['path']) ||
            ctype_space(Config::Config()['log']['path']) ||
            !file_exists(Config::Config()['log']['path'])
        ) { return; }
        $logLevel = (
            Config::Config()['log']['level'] == '' ||
            ctype_space(Config::Config()['log']['level']) ||
            !ctype_digit(Config::Config()['log']['level'])
        ) ? LogLevel::ERROR : Config::Config()['log']['level'];
        if ($level >= $logLevel)
        {
            $messageType = gettype($message);
            //Message format: [LEVEL @ TIME] | [FILE:LINE] [MESSAGE]
            $logMessage = "[" . LogLevel::GetName($level) . " @ " . date("Y-m-d H:i:s") . "] | [" . basename(debug_backtrace()[0]['path']) . ":" . debug_backtrace()[0]['line'] . "] [" . $messageType . "] " . print_r($message, true) . PHP_EOL;
            file_put_contents(Config::Config()['log']['path'], $logMessage, FILE_APPEND);
        }
    }
}