<?php
require_once __DIR__ . '/../../databaseHelper.php';

class webfilemanager_users extends BasicDatabaseHelper
{
    protected $table = __CLASS__;

    public $id;
    public $username;
    public $password;
    public $sessionToken;
    public $admin;
}