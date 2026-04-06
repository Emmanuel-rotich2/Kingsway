<?php
namespace App\Database;
use App\Config\Config;

use PDO;
use PDOException;
use Exception;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $this->connection = new PDO(
                "mysql:host=" . Config::get('DB_HOST') .
                ";port=" . Config::get('DB_PORT') .
                ";dbname=" . Config::get('DB_NAME') .
                ";charset=utf8mb4",
                Config::get('DB_USER'),
                Config::get('DB_PASS'),
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                )
            );
        } catch (PDOException $e) {
            error_log("Connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    private function __clone()
    {
        // Prevent cloning of the singleton instance.
        // This method is declared private to avoid external cloning,
        // but we also throw an exception to guard against invocation
        // via reflection or other bypass mechanisms.
        throw new Exception("Cloning of " . __CLASS__ . " is not allowed.");
    }

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    public function commit()
    {
        return $this->connection->commit();
    }

    public function rollback()
    {
        return $this->connection->rollBack();
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }
}
