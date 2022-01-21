<?php
#region Root user check
if (posix_getuid() != 0)
{
    echo 'This script must be run as root.' . PHP_EOL;
    exit(1);
}
#endregion

// $ROOT_DIR = dirname(__FILE__);
const ROOT_DIR = __DIR__;
const CONFIG_FILE_PATH = ROOT_DIR . '/_assets/configuration/config.json';

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
                        // ($defaultIsNull && ($value['type'] == 'string' && !$value['required']) ? '' : ' [' . $value['default'] . ']') .
                        ($defaultIsNull && $value['required'] ? '' : ' [' . $value['default'] . ']') .
                        ($defaultIsNull && $value['required'] ? ' (required)' : '') .
                        ': '
                    );
                    $userInputType = GetTypeFromString($userInput);
                    if ($value['required'] && $userInputType == 'NULL')
                    {
                        if (!$defaultIsNull) { break; }
                        echo 'Input is required. Please try again.' . PHP_EOL;
                    }
                    else if ($userInputType != $value['type'])
                    {
                        if (!$defaultIsNull || ($value['type'] == 'string' && $defaultIsNull && $userInputType == 'NULL')) { break; }
                        echo 'Input must be of type \'' .
                            $value['type'] .
                            '\'' .
                            ($defaultIsNull ? ' or \'NULL\'' : '') .
                            ', not \'' .
                            $userInputType .
                            '\'. Please try again.' .
                            PHP_EOL;
                    }
                    else
                    {
                        $userInput = ConvertToType($value['type'], $userInput);
                        break;
                    }
                }
                if ($userInputType == 'NULL')
                {
                    $userInput = $defaultIsNull ? '' : $value['default'];
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

#region Config generation
function ConfigGeneration()
{
    echo '===Config generation===' . PHP_EOL;
    if (!file_exists(ROOT_DIR . '/_assets/configuration/config.template.json'))
    {
        echo 'The configuration template file was not found in \'_assets/configuration/config.template.json\'.' . PHP_EOL . 'Please make sure it exists and try again.' . PHP_EOL;
        exit(1);
    }
    $template = json_decode(file_get_contents(ROOT_DIR . '/_assets/configuration/config.template.json'), true);
    $config = PopulateConfig(new stdClass(), $template);
    $jsonConfig = json_encode($config, JSON_PRETTY_PRINT);
    if (!file_put_contents(CONFIG_FILE_PATH, $jsonConfig))
    {
        echo 'Failed to write configuration file. Please create it manually and place it into \'_assets/configuration/config.json\'' . PHP_EOL;
        echo $jsonConfig . PHP_EOL;
    }
    chown(CONFIG_FILE_PATH, 'www-data');
    if ($config->log->path !== null) { chown($config->log->path, 'www-data'); } //Not ideal to assume the location of this data.

    echo 'Configuration file written successfully.' . PHP_EOL;
}
#endregion

#region Webserver configuration
function WebserverConfiguration()
{
    echo '===Webserver configuration===' . PHP_EOL;
    $hadErrors = false;

    if (!file_exists(CONFIG_FILE_PATH))
    {
        echo 'The configuration file was not found in \'_assets/configuration/config.json\'.' . PHP_EOL . 'Please make sure it exists and try again.' . PHP_EOL;
        $hadErrors = true;
    }

    $config = json_decode(file_get_contents(CONFIG_FILE_PATH), true);
    if (!isset($config['site']['path']))
    {
        echo 'The configuration file is missing the \'site.path\' key.' . PHP_EOL . 'Please make sure it exists and try again.' . PHP_EOL;
        $hadErrors = true;
    }
    $sitePath = $config['site']['path'];

    $apiV1Path = $sitePath . '/api/v1';
    $rootLocationBlock = $sitePath == '' ? '/' : $sitePath;
    $NGINXSnippit = "\r\"server {
    location $rootLocationBlock {
        #region API
        #region V1
        location $apiV1Path/_helpers { deny all; }
        location $apiV1Path/directory { try_files \$uri \$uri/ $apiV1Path/directory/index.php?\$query_string; }
        location $apiV1Path/file { try_files \$uri \$uri/ $apiV1Path/file/index.php?\$query_string; }
        #endregion
        location $sitePath/api { try_files \$uri \$uri/ =404; }
        #endregion

        #region UI
        location $sitePath/view/directory { try_files \$uri \$uri/ $sitePath/view/directory/index.php?\$query_string; }
        location $sitePath/view/file { try_files \$uri \$uri/ $sitePath/view/file/index.php?\$query_string; }
        #endregion

        #region Misc
        location $sitePath/_assets { deny all; }
        location $sitePath/_storage { deny all; }
        location $sitePath/_updates { deny all; }
        location $sitePath/setup.php { deny all; }
        location $sitePath/dist { deny all; }
        location $sitePath/dist.zip { deny all; }
        #endregion

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

    echo 'Webserver configuration complete' . ($hadErrors ? ' with errors.' : '.') . PHP_EOL;
}
#endregion

#region Database setup
function DatabaseSetup()
{
    echo '===Database setup===' . PHP_EOL;
    $hadErrors = false;

    if (!file_exists(CONFIG_FILE_PATH))
    {
        echo 'The configuration file was not found in \'_assets/configuration/config.json\'.' . PHP_EOL . 'Please make sure it exists and try again.' . PHP_EOL;
        exit(1);
    }

    $config = json_decode(file_get_contents(CONFIG_FILE_PATH), true);
    if (!isset($config['database']['host']) || !isset($config['database']['username']) || !isset($config['database']['password']) || !isset($config['database']['database']))
    {
        echo 'The configuration file is missing some required values.' . PHP_EOL . 'Please make sure it contains the following values: \'database.host\', \'database.username\', \'database.password\', \'database.database\'' . PHP_EOL;
        $hadErrors = true;
    }
    $host = $config['database']['host'];
    $username = $config['database']['username'];
    $password = $config['database']['password'];
    $database = $config['database']['database'];
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);

    $tablesPath = ROOT_DIR . '/_assets/database/tables';
    if (!file_exists($tablesPath) && !is_dir($tablesPath))
    {
        echo 'The database tables directory was not found in \'.' . $tablesPath . '\'.' . PHP_EOL . 'Please make sure it exists and try again.' . PHP_EOL;
        $hadErrors = true;
    }
    $directories = array_filter(scandir($tablesPath), fn($item) => is_dir($tablesPath . '/' . $item) && $item != '.' && $item != '..');
    //Create tables
    foreach ($directories as $directory)
    {
        $files = array_filter(scandir($tablesPath . '/' . $directory), fn($item) => is_file($tablesPath . '/' . $directory . '/' . $item) && $item != '.' && $item != '..');
        if (!in_array('table.sql', $files)) { continue; }
        $tableSql = file_get_contents($tablesPath . '/' . $directory . '/table.sql');
        echo 'Creating table \'' . $directory . '\' if it does not exist...' . PHP_EOL;
        try
        {
            $pdo->prepare($tableSql)->execute();
        }
        catch (PDOException $e)
        {
            echo 'Error creating table \'' . $directory . '\': ' . $e->getMessage() . PHP_EOL;
            $hadErrors = true;
        }
    }
    //Add restraints
    foreach ($directories as $directory)
    {
        $files = array_filter(scandir($tablesPath . '/' . $directory), fn($item) => is_file($tablesPath . '/' . $directory . '/' . $item) && $item != '.' && $item != '..');
        if (!in_array('restraints.sql', $files)) { continue; }
        $restraintsSql = file_get_contents($tablesPath . '/' . $directory . '/restraints.sql');
        echo 'Adding restraints to table \'' . $directory . '\'...' . PHP_EOL;
        try
        {
            $pdo->prepare($restraintsSql)->execute();
        }
        catch (PDOException $e)
        {
            echo 'Error adding restraints to table \'' . $directory . '\': ' . $e->getMessage() . PHP_EOL;
            $hadErrors = true;
        }
    }
    //Populate tables
    foreach ($directories as $directory)
    {
        $files = array_filter(scandir($tablesPath . '/' . $directory), fn($item) => is_file($tablesPath . '/' . $directory . '/' . $item) && $item != '.' && $item != '..');
        if (!in_array('populate.sql', $files)) { continue; }
        $populateSql = file_get_contents($tablesPath . '/' . $directory . '/populate.sql');
        echo 'Populating table \'' . $directory . '\'...' . PHP_EOL;
        try
        {
            $pdo->prepare($populateSql)->execute();
        }
        catch (PDOException $e)
        {
            echo 'Failed to populate table \'' . $directory . '\'' . PHP_EOL;
            $hadErrors = true;
        }
    }

    echo 'Database setup complete' . ($hadErrors ? ' with errors.' : '.') . PHP_EOL;
}
#endregion

#region Misc setup
function MiscSetup()
{
    echo '===Misc setup===' . PHP_EOL;
    $hadErrors = false;
    $config = json_decode(file_get_contents(CONFIG_FILE_PATH), true);

    if (!isset($config['log']['path']))
    {
        touch($config['log']['path']);
        chown($config['log']['path'], 'www-data');
        chgrp($config['log']['path'], 'www-data');
    }

    echo 'Creating \'_storage\' directory if it does not exist...' . PHP_EOL;
    if (!file_exists(ROOT_DIR . '/_storage'))
    {
        mkdir(ROOT_DIR . '/_storage');
        chown(ROOT_DIR . '/_storage', 'www-data');
        chgrp(ROOT_DIR . '/_storage', 'www-data');
    }
    echo 'Creating \'_storage\thumbnails\' directory if it does not exist...' . PHP_EOL;
    if (!file_exists(ROOT_DIR . '/_storage/thumbnails'))
    {
        mkdir(ROOT_DIR . '/_storage/thumbnails');
        chown(ROOT_DIR . '/_storage/thumbnails', 'www-data');
        chgrp(ROOT_DIR . '/_storage/thumbnails', 'www-data');
    }
    
    //Get composer setup working
    echo 'Installing composer dependencies...' . PHP_EOL;
    $shellResult = shell_exec('cd \'' . ROOT_DIR . '/_assets/libs\' && composer install');
    if ($shellResult === null || $shellResult === false)
    {
        echo 'Failed to install composer dependencies.' . PHP_EOL;
        $hadErrors = true;
    }
    //Return to root directory.
    shell_exec('cd \'' . ROOT_DIR . '\'');

    echo 'Misc setup complete' . ($hadErrors ? ' with errors.' : '.') . PHP_EOL;
}
#endregion

#region Configure specific settings check
global $setupIsImported;
$configureStage =
    isset($args['configure']) && GetTypeFromString($args['configure']) == 'string' ?
    strtolower($args['configure']) :
    ($setupIsImported !== true ? 'all' : '');
switch ($configureStage)
{
    case 'config':
        ConfigGeneration();
        break;
    case 'webserver':
        WebserverConfiguration();
        break;
    case 'database':
        DatabaseSetup();
        break;
    case 'misc':
        MiscSetup();
        break;
    case 'all':
        ConfigGeneration();
        WebserverConfiguration();
        DatabaseSetup();
        MiscSetup();
        break;
    default:
        if ($setupIsImported !== true) { echo 'Invalid configuration stage specified.' . PHP_EOL; }
        break;
}
#endregion
