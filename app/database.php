<?php

// No incluir config.php aquí para evitar referencia circular

// ========================================
// CLASE PRINCIPAL - Database
// ========================================
class Database
{
    private static $instance = null;
    private $connection;

    // ========================================
    // CONSTRUCTOR Y SINGLETON
    // ========================================
    private function __construct()
    {
        $this->establishConnection();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ========================================
    // CONEXIÓN A BASE DE DATOS
    // ========================================
    private function establishConnection()
    {
        try {
            $dsn = $this->buildDSN();
            $this->connection = new PDO($dsn, DB_USER, DB_PASSWORD, $this->getPDOOptions());
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    private function buildDSN()
    {
        return "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    }

    private function getPDOOptions()
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    // ========================================
    // MÉTODOS PÚBLICOS
    // ========================================
    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Error en consulta: " . $e->getMessage());
        }
    }

    public function execute($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception("Error en ejecución: " . $e->getMessage());
        }
    }

    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    // ========================================
    // MANEJO DE TABLAS
    // ========================================
    public function tableExists($tableName)
    {
        $sql = "SHOW TABLES LIKE '" . $tableName . "'";
        $result = $this->query($sql);
        return !empty($result);
    }

    public function createQueriesTable()
    {
        if (!$this->tableExists('queries')) {
            $sql = $this->getQueriesTableSchema();
            return $this->execute($sql);
        }
        return true;
    }

    // ========================================
    // MÉTODOS PRIVADOS
    // ========================================
    private function getQueriesTableSchema()
    {
        return "CREATE TABLE queries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            sql_query TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}

// La función getDB() ya está definida en config.php
