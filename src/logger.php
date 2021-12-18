<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logLevel.php';

class Logger
{
    public static function Log($message, int $level): void
    {
        if (ctype_space(Config::LOG_FILE) || !file_exists(Config::LOG_FILE)) { return; }
        if ($level >= Config::LOG_LEVEL)
        {
            $messageType = gettype($message);
            //Message format: [LEVEL @ TIME] | [FILE:LINE] [MESSAGE]
            $logMessage = "[" . LogLevel::GetName($level) . " @ " . date("Y-m-d H:i:s") . "] | [" . basename(debug_backtrace()[1]['file']) . ":" . debug_backtrace()[1]['line'] . "] [" . $messageType . "] " . (
                $messageType === 'array' || $messageType === 'object' ?
                print_r($message, true) :
                $message
            );
            file_put_contents(Config::LOG_FILE, $logMessage, FILE_APPEND);
        }
    }
}