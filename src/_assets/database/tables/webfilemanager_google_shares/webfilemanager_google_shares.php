<?php
require_once __DIR__ . '/../../databaseHelper.php';

class webfilemanager_google_shares extends BasicDatabaseHelper
{
    protected $table = __CLASS__;

    public $id;
    public $sid;
    public $user;
}