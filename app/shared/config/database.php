<?php
//require_once 'constants.php'; // Load constants
require_once __DIR__ . '/constants.php';

class Database
{
    private static $instance = null;
    private static $lastError = null;
    private $conn;

    private function __construct()
    {
        self::$lastError = null;

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

            $this->conn = mysqli_init();
            if (!$this->conn) {
                throw new Exception("Database initialization failed");
            }

            $this->conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            $this->conn->options(MYSQLI_OPT_READ_TIMEOUT, 5);

            if (!$this->conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306)) {
                throw new Exception("DB Connection failed: " . $this->conn->connect_error);
            }

            $this->conn->set_charset("utf8mb4");
            $this->conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        } catch (Exception $e) {
            self::$lastError = $e->getMessage();
            error_log($e->getMessage());
            $this->conn = null;
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public static function getLastError()
    {
        return self::$lastError;
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
