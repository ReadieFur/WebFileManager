<?php
require_once __DIR__ . '/../logger/logger.php';
require_once __DIR__ . '/../logger/logLevel.php';

//Tweaked from: https://github.com/kOFReadie/api-readie/blob/main/database/databaseHelper.php
abstract class BasicDatabaseHelper
{
    public const INSERT_ERROR = 'INSERT_FAILED';
    public const UPDATE_ERROR = 'UPDATE_FAILED';
    public const SELECT_ERROR = 'SELECT_FAILED';
    public const DELETE_ERROR = 'DELETE_FAILED';

    private PDO $pdoConn;
    private string $database;
    private ?array $lastSQLError;
    private ?Exception $lastException;

    protected $table;

    public function __construct(bool $manualCall = false, string $host = null, string $database = null, string $username = null, string $password = null)
    {
        if ($manualCall)
        {
            $this->pdoConn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            $this->database = $database;
            $this->lastSQLError = null;
            $this->lastException = null;
        }
        //This manual call check is needed so that when PDO creates an instance of this abstract class, it doesn't try to connect to the database again.
    }

    public function GetLastSQLError(): ?array { return $this->lastSQLError; }
    public function GetLastException(): ?Exception { return $this->lastException; }

    public function Insert(array $_columns): bool
    {
        try
        {
            $keys = array_keys($_columns);
            $keysWithColon = array();
            foreach ($keys as $value) { array_push($keysWithColon, ':' . $value); }

            $sql = $this->pdoConn->prepare('INSERT INTO ' . $this->table . '(' . implode(',', $keys) . ') values(' . implode(',', $keysWithColon) . ')');
            foreach ($_columns as $key => $value) { $sql->bindValue(':' . $key, $value, PDO::PARAM_STR); }
            
            if (!$sql->execute())
            {
                $this->lastSQLError = $sql->errorInfo();
                return false;
            }
            else { return true; }
        }
        catch (Exception $e)
        {
            $this->lastException = $e;
            return false;
        }
    }

    public function Update(array $_columns, array $_where): bool
    {
        try
        {
            $columnSQL = array();
            foreach ($_columns as $key => $value) { array_push($columnSQL, $key . '=:' . $key); }

            $whereSQL = array();
            $whereSQLCounter = 0;
            foreach ($_where as $key => $value)
            {
                if ($whereSQLCounter % 2) { array_push($whereSQL, $value); }
                else { array_push($whereSQL, $key . '=:' . $key); }
                $whereSQLCounter++;
            }
            
            $sql = $this->pdoConn->prepare('UPDATE ' . $this->table . ' SET ' . implode(',', $columnSQL) . ' WHERE ' . implode(' ', $whereSQL));
            foreach ($_columns as $key => $value) { $sql->bindValue(':' . $key, $value, PDO::PARAM_STR); }
            
            $whereSQLCounter = 0;
            foreach ($_where as $key => $value)
            {
                if (!($whereSQLCounter % 2))
                {
                    $sql->bindValue(':' . $key, $value, PDO::PARAM_STR);
                }
                $whereSQLCounter++;
            }

            // Logger::Log('Executing SQL: ' . $sql->queryString, LogLevel::DEBUG);

            if (!$sql->execute())
            {
                $this->lastSQLError = $sql->errorInfo();
                return false;
            }
            else { return true; }
        }
        catch (Exception $e)
        {
            $this->lastException = $e;
            return false;
        }
    }

    public function Select(?array $_where): false | array
    {
        try
        {
            $sql = '';

            if ($_where != null)
            {
                $whereSQL = array();
                $whereSQLCounter = 0;
                foreach ($_where as $key => $value)
                {
                    if ($whereSQLCounter % 2) { array_push($whereSQL, $value); }
                    else { array_push($whereSQL, $key . '=:' . $key); }
                    $whereSQLCounter++;
                }
    
                $sql = $this->pdoConn->prepare('SELECT * FROM ' . $this->table . ' WHERE ' . implode(' ', $whereSQL));
    
                $whereSQLCounter = 0;
                foreach ($_where as $key => $value)
                {
                    if (!($whereSQLCounter % 2))
                    {
                        $sql->bindValue(':' . $key, $value, PDO::PARAM_STR);
                    }
                    $whereSQLCounter++;
                }
            }
            else
            {
                $sql = $this->pdoConn->prepare('SELECT * FROM ' . $this->table);
            }

            if (!$sql->execute())
            {
                $this->lastSQLError = $sql->errorInfo();
                return false;
            }

            $results = array();
            for ($i = 0; $i < $sql->rowCount(); $i++) { array_push($results, $sql->fetchObject($this->table)); }

            return $results;
        }
        catch (Exception $e)
        {
            $this->lastException = $e;
            return false;
        }
    }

    public function Delete(array $_where, bool $deleteAllForeign = false): bool
    {
        try
        {
            $whereSQL = array();
            $whereSQLCounter = 0;
            foreach ($_where as $key => $value)
            {
                if ($whereSQLCounter % 2) { array_push($whereSQL, $value); }
                else { array_push($whereSQL, $key . '=:' . $key); }
                $whereSQLCounter++;
            }
    
            $sql = $this->pdoConn->prepare(($deleteAllForeign ? 'SELECT *' : 'DELETE') . ' FROM ' . $this->table . ' WHERE ' . implode(' ', $whereSQL));
            
            $whereSQLCounter = 0;
            foreach ($_where as $key => $value)
            {
                if (!($whereSQLCounter % 2))
                {
                    $sql->bindValue(':' . $key, $value, PDO::PARAM_STR);
                }
                $whereSQLCounter++;
            }
    
            if (!$sql->execute())
            {
                $this->lastSQLError = $sql->errorInfo();
                return false;
            }
            else if (!$deleteAllForeign || $sql->rowCount() <= 0)
            {
                $this->lastSQLError = null;
                return true;
            }
    
            for ($i = 0; $i < $sql->rowCount(); $i++)
            {
                $deletionResult = $this->DeleteForeign($this->table, $sql->fetchObject());
                if (!$deletionResult) { return false; }
            }
    
            return true;
        }
        catch (Exception $e)
        {
            $this->lastException = $e;
            return false;
        }
    }

    private function DeleteForeign(string $_table, object $_row): bool
    {
        try
        {
            $sql = $this->pdoConn->prepare("select fks.constraint_schema as foreign_database, fks.table_name as foreign_table, group_concat(kcu.column_name order by position_in_unique_constraint separator ', ') as foreign_key, fks.unique_constraint_schema as referenced_database, fks.referenced_table_name as referenced_table, REFERENCED_COLUMN_NAME as referenced_key
            from information_schema.referential_constraints fks
            join information_schema.key_column_usage kcu
                on fks.constraint_schema = kcu.table_schema
                and fks.table_name = kcu.table_name
                and fks.constraint_name = kcu.constraint_name
                -- where fks.constraint_schema = 'database name'
            group by fks.constraint_schema,
                fks.table_name,
                fks.unique_constraint_schema,
                fks.referenced_table_name,
                fks.constraint_name
            order by fks.constraint_schema,
                fks.table_name;");
            if (!$sql->execute())
            {
                $this->lastSQLError = $sql->errorInfo();
                return false;
            }
            for ($i = 0; $i < $sql->rowCount(); $i++)
            {
                $fkr = $sql->fetchObject();
                if ($fkr->referenced_table == $_table)
                {
                    $rows = $this->pdoConn->prepare('SELECT * FROM ' . $fkr->foreign_table . ' WHERE ' . $fkr->foreign_key . ' = \'' . get_object_vars($_row)[$fkr->referenced_key] . '\'');
                    if (!$rows->execute())
                    {
                        $this->lastSQLError = $rows->errorInfo();
                        return false;
                    }
                    for ($j = 0; $j < $rows->rowCount(); $j++) { $this->DeleteForeign($fkr->foreign_table, $rows->fetchObject()); }
                }
            }
    
            $sql = $this->pdoConn->prepare(
                'SELECT k.column_name '.
                'FROM information_schema.table_constraints t '.
                'JOIN information_schema.key_column_usage k '.
                'USING(constraint_name,table_schema,table_name) '.
                'WHERE t.constraint_type=\'PRIMARY KEY\' '.
                'AND t.table_schema=\'' . $this->database . '\' '.
                'AND t.table_name=\'' . $_table . '\''
            );
            if (!$sql->execute())
            {
                $this->lastSQLError = $sql->errorInfo();
                return false;
            }
            else if ($sql->rowCount() <= 0)
            {
                $this->lastSQLError = null;
                return false;
            }
            $primaryKeyName = $sql->fetchObject()->column_name;
    
            $pk = get_object_vars($_row)[$primaryKeyName];
            $deleteSQL = $this->pdoConn->prepare('DELETE FROM ' . $_table . ' WHERE ' . $primaryKeyName . ' = \'' . $pk . '\'');
            if (!$deleteSQL->execute())
            {
                $this->lastSQLError = $deleteSQL->errorInfo();
                return false;
            }
            else { return true; }
        }
        catch (Exception $e)
        {
            $this->lastException = $e;
            return false;
        }
    }
}