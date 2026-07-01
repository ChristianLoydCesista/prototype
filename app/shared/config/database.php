<?php
//require_once 'constants.php'; // Load constants
require_once __DIR__ . '/constants.php';

class Database
{
    private static $instance = null;
    private $conn;

    private function __construct()
    {
        try {
            if (!defined('DB_HOST')) {
                throw new Exception("Database configuration missing");
            }
            if (!defined('DB_USER')) {
                throw new Exception("Database user configuration missing");
            }
            if (!defined('DB_PASS')) {
                throw new Exception("Database password configuration missing");
            }
            if (!defined('DB_NAME')) {
                throw new Exception("Database name configuration missing");
            }
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->conn->connect_error) {
                throw new Exception("DB Connection failed: " . $this->conn->connect_error);
            }

            $this->conn->set_charset("utf8mb4");
            $this->conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        } catch (Exception $e) {
            error_log($e->getMessage());

            // Optional: show safe message instead of crashing
            die("System temporarily unavailable. Please try again later.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function closeConnection()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Helper function for procedural-style access
function getDB()
{
    return Database::getInstance()->getConnection();
}
