<?php
#region Root user check
if (posix_getuid() != 0)
{
    echo 'This script must be run as root.' . PHP_EOL;
    exit(1);
}
#endregion

#region Args
$args = array();
for ($i = 0; $i < count($argv); $i++)
{ 
    if (substr($argv[$i], 0, 1) == '-' && isset($argv[$i + 1]) && substr($argv[$i + 1], 1) != '-')
    {
        $args[substr($argv[$i], 1)] = $argv[$i + 1];
        $i++;
    }
}
#endregion

#region Helper functions
function GetTypeFromString(string $string)
{
    $string = strtolower($string);
    if (in_array($string, ['true', 'false', 't', 'f', 'yes', 'no', 'y', 'n'])) { return 'boolean'; }
    else if (ctype_digit($string)) { return 'integer'; }
    else if (is_numeric($string)) { return 'float'; }
    //ctype_space() may not be good as the data is still a string, but I see it as empty so I will still return null as the data type here.
    else if (in_array($string, ['null', '']) || ctype_space($string)) { return 'NULL'; }
    else { return 'string'; }
}

function ConvertToType(string $type, $value)
{
    switch ($type)
    {
        case 'boolean':
            return (bool)$value;
        case 'integer':
            return (int)$value;
        case 'float':
            return (float)$value;
        case 'NULL':
            return null;
        default:
            return $value;
    }
}

function PopulateConfig(object $config, array $template)
{
    foreach ($template as $key => $value)
    {
        if (is_array($value))
        {
            if (
                isset($value['type']) &&
                isset($value['description']) &&
                isset($value['default']) &&
                isset($value['required'])
            )
            {
                $defaultIsNull = $value['default'] == '';
                $userInput = '';
                while (true)
                {
                    //Message format: "{description} [{default}] ({required}): "
                    $userInput = readline(
                        $value['description'] .
                        ($defaultIsNull ? '' : ' [' . $value['default'] . ']') .
                        ($defaultIsNull && $value['required'] ? ' (required)' : '') .
                        ': '
                    );
                    if ($value['required'] && ($userInput == '' || ctype_space($userInput)))
                    {
                        if (!$defaultIsNull) { break; }
                        echo 'Input is required. Please try again.' . PHP_EOL;
                    }
                    else if (GetTypeFromString($userInput) !== $value['type'])
                    {
                        if (!$defaultIsNull && $userInput == '' || ctype_space($userInput)) { break; }
                        echo 'Input must be of type \'' . $value['type'] . '\'. Please try again.' . PHP_EOL;
                    }
                    else
                    {
                        $userInput = ConvertToType($value['type'], $userInput);
                        break;
                    }
                }
                if ($userInput == '' || ctype_space($userInput))
                {
                    $userInput = $defaultIsNull ? null : $value['default'];
                }
                $config->{$key} = $userInput;
            }
            else
            {
                $config->{$key} = new stdClass();
                PopulateConfig($config->{$key}, $value);
            }
        }
        else
        {
            echo 'Invalid template.' . PHP_EOL;
            exit(1);
        }
    }
    return $config;
}
#endregion

#region Webserver configuration
function WebserverConfiguration()
{
    echo '===Webserver configuration===' . PHP_EOL;

    $sitePath = readline('Enter the path to the site: ');
    if ($sitePath == '' || ctype_space($sitePath)) { $sitePath = ''; }
    else if (substr($sitePath, 0, 1) != '/') { $sitePath = '/' . $sitePath; }

    $apiV1Path = $sitePath . '/api/v1';
    $rootLocationBlock = $sitePath == '' ? '/' : $sitePath;
    $NGINXSnippit = "\r\"server {
    location $rootLocationBlock {
        #region API
        #region V1
        location $apiV1Path/directory { try_files \$uri \$uri/ $apiV1Path/directory/index.php?\$query_string; }
        location $apiV1Path/file { try_files \$uri \$uri/ $apiV1Path/file/index.php?\$query_string; }
        #endregion
        location $sitePath/api { try_files \$uri \$uri/ =404; }
        #endregion

        location $sitePath/_assets { deny all; }

        try_files \$uri \$uri/ /index.php\$query_string;

        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php8.0-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }
}\"";

    echo "Please configure your web server to have the same functionality as the following NGINX configuration snippet. If you are using NGINX as your web server, you may still need to tweak this snippit to suit your needs." . PHP_EOL;
    echo $NGINXSnippit . PHP_EOL;
    echo "(Please read the message above if you have not already)" . PHP_EOL;
}
#endregion

#region Config generation
function ConfigGeneration()
{
    echo '===Config generation===' . PHP_EOL;
    if (!file_exists(__DIR__ . '/_assets/configuration/config.template.json'))
    {
        echo 'The configuration template file was not found in \'_assets/configuration/config.template.json\'.\nPlease make sure it exists and try again.' . PHP_EOL;
        exit(1);
    }
    $template = json_decode(file_get_contents(__DIR__ . '/_assets/configuration/config.template.json'), true);
    $config = PopulateConfig(new stdClass(), $template);
    $jsonConfig = json_encode($config, JSON_PRETTY_PRINT);
    if (!file_put_contents(__DIR__ . '/_assets/configuration/config.json', $jsonConfig))
    {
        echo 'Failed to write configuration file. Please create it manually and place it into \'_assets/configuration/config.json\'' . PHP_EOL;
        echo $jsonConfig . PHP_EOL;
    }
    echo 'Configuration file written successfully.' . PHP_EOL;
}
#endregion

#region Configure specific settings check
$configureStage = isset($args['configure']) && GetTypeFromString($args['configure']) == 'string' ? strtolower($args['configure']) : 'all';
switch ($configureStage)
{
    case 'webserver':
        WebserverConfiguration();
        break;
    case 'config':
        ConfigGeneration();
        break;
    default: //All
        WebserverConfiguration();
        ConfigGeneration();
        break;
}
#endregion
