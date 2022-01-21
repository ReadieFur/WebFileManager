<?php
require_once __DIR__ . '/../logger/logLevel.php';

class Config
{
    private static array $config;
    private static array $configTemplate;

    private static function GetTypeFromString(string $string)
    {
        $string = strtolower($string);
        if (in_array($string, ['true', 'false', 't', 'f', 'yes', 'no', 'y', 'n'])) { return 'boolean'; }
        else if (ctype_digit($string)) { return 'integer'; }
        else if (is_numeric($string)) { return 'float'; }
        //ctype_space() may not be good as the data is still a string, but I see it as empty so I will still return null as the data type here.
        else if (in_array($string, ['null', '']) || ctype_space($string)) { return 'NULL'; }
        else { return 'string'; }
    }

    private static function Log(string $message)
    {
        if (isset(self::$config['log']['file']) && (!ctype_space(self::$config['log']['file']) || file_exists(self::$config['log']['file'])))
        {
            $logMessage = "[" . LogLevel::GetName(LogLevel::ERROR) . " @ " . date("Y-m-d H:i:s") . "] | [config.php] [string] " . $message . PHP_EOL;
            file_put_contents(self::$config['log']['file'], $logMessage, FILE_APPEND);
        }
    }

    //Its probably not that efficent to check the config for every single request but as this is a rather low traffic site it should be fine.
    private static function CheckConfig(array $config, array $template)
    {
        foreach ($template as $key => $value)
        {
            if (!array_key_exists($key, $config))
            {
                $errorMessage = "Missing key: " . $key;
                self::Log($errorMessage);
                throw new Exception($errorMessage);
            }
            else if (is_array($value))
            {
                if (
                    isset($value['type']) &&
                    isset($value['description']) &&
                    isset($value['default']) &&
                    isset($value['required'])
                )
                {
                    $dataType = self::GetTypeFromString($config[$key]);
                    if ($dataType != $value['type'])
                    {
                        if (!($value['type'] == 'string' && self::GetTypeFromString($value['default']) == 'NULL' && $dataType == 'NULL'))
                        {
                            $errorMessage = "Invalid type for key: " . $key . $value['type'] . self::GetTypeFromString($value['default']) . $dataType;
                            self::Log($errorMessage);
                            throw new Exception($errorMessage);
                        }
                    }
                    else if ($value['required'] && ($dataType == 'NULL'))
                    {
                        $errorMessage = "A value is required for key: " . $key;
                        self::Log($errorMessage);
                        throw new Exception($errorMessage);
                    }
                }
                else
                {
                    self::CheckConfig($config[$key], $value);
                }
            }
            else
            {
                $errorMessage = "Invalid template for key: " . $key;
                self::Log($errorMessage);
                throw new Exception($errorMessage);
            }
        }
    }

    public static function LoadConfig()
    {
        if (file_exists(__DIR__ . '/config.template.json') && file_exists(__DIR__ . '/config.json'))
        {
            self::$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
            self::$configTemplate = json_decode(file_get_contents(__DIR__ . '/config.template.json'), true);
            self::CheckConfig(self::$config, self::$configTemplate);
        }
        else
        {
            $errorMessage = 'Config file not found. Please run the setup script.';
            self::Log($errorMessage);
            throw new Exception($errorMessage);
        }
    }

    public static function Config(): array { return self::$config; }
    public static function ConfigTemplate(): array { return self::$configTemplate; }
}
//Load the config automatically for any other files that need it.
Config::LoadConfig();