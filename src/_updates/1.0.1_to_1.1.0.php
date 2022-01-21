<?php
$setupIsImported = true;
require_once __DIR__ . '/../setup.php';

$hadErrors = false;

$config = json_decode(file_get_contents(__DIR__ . '/../_assets/configuration/config.json'), true);

//Update config.
$configTemplate = json_decode(file_get_contents(__DIR__ . '/../_assets/configuration/config.template.json'), true);
$gapi_client_id = PopulateConfig(new stdClass(), $configTemplate['gapi'])->client_id;
$config['gapi']['client_id'] = $gapi_client_id;
$jsonConfig = json_encode($config, JSON_PRETTY_PRINT);
if (!file_put_contents(__DIR__ . '/../_assets/configuration/config.json', $jsonConfig))
{
    echo 'Failed to write configuration file. Please create it manually and place it into \'_assets/configuration/config.json\'' . PHP_EOL;
    echo $jsonConfig . PHP_EOL;
}

//Update database.
if (!isset($config['database']['host']) || !isset($config['database']['username']) || !isset($config['database']['password']) || !isset($config['database']['database']))
{
    echo 'The configuration file is missing some required values.' . PHP_EOL . 'Please make sure it contains the following values: \'database.host\', \'database.username\', \'database.password\', \'database.database\'' . PHP_EOL;
    $hadErrors = true;
}
$host = $config['database']['host'];
$username = $config['database']['username'];
$password = $config['database']['password'];
$database = $config['database']['database'];

$pdo = new PDO(
    'mysql:host=' . $host .
    ';dbname=' . $database,
    $username,
    $password
);

// try { $pdo->prepare('UPDATE `webfilemanager_shares` SET `share_type` = 2 WHERE `share_type` = 1')->execute(); } catch (Exception $e) { $hadErrors = true; } //This does not need to be changed as it is still a public share but the timeout check is always run now.
try { $pdo->prepare('UPDATE `webfilemanager_shares` SET `share_type` = 1 WHERE `share_type` = 0')->execute(); } catch (Exception $e) { $hadErrors = true; }

//Create tables
$tableSql = file_get_contents(__DIR__ . '/../_assets/database/tables/webfilemanager_google_shares/table.sql');
try { $pdo->prepare($tableSql)->execute(); } catch (PDOException $e) { $hadErrors = true; }
//Add restraints
$restraintsSql = file_get_contents(__DIR__ . '/../_assets/database/tables/webfilemanager_google_shares/restraints.sql');
try { $pdo->prepare($restraintsSql)->execute(); } catch (PDOException $e) { $hadErrors = true; }

//Update libs.
$shellResult = shell_exec('cd \'' . __DIR__ . '/../_assets/libs\' && composer install && composer update');
if ($shellResult === null || $shellResult === false)
{
    echo 'Failed to install composer dependencies.' . PHP_EOL;
    $hadErrors = true;
}

echo 'Update complete' . ($hadErrors ? ' with errors.' : '.') . PHP_EOL;