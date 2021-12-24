<?php
require_once __DIR__ . '/../../databaseHelper.php';

class webfilemanager_shares extends BasicDatabaseHelper
{
    protected $table = __CLASS__;

    public $id;
    public $uid;
    public $pid;
    public $path;
    public $shareType;
    public $expiryTime;
}