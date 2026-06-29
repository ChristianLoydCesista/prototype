<?php
require_once 'constants.php'; // Load constants

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            // throw exception so bootstrap or caller can decide what to do
            throw new Exception("Database connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
        // use native types wherever possible
        $this->conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Helper function for procedural-style access
function getDB() {
    return Database::getInstance()->getConnection();
}
?>