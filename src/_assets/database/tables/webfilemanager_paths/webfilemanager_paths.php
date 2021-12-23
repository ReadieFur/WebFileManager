<?php
require_once __DIR__ . '/../../databaseHelper.php';

class webfilemanager_paths extends BasicDatabaseHelper
{
    protected $table = __CLASS__;

    public $id;
    public $web_path;
    public $local_path;
}