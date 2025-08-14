<?php

require_once 'config.php';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    // Patrón Singleton para una sola conexión
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Obtener la conexión PDO
    public function getConnection()
    {
        return $this->connection;
    }

    // Ejecutar consulta SELECT
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

    // Ejecutar consulta INSERT, UPDATE, DELETE
    public function execute($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception("Error en ejecución: " . $e->getMessage());
        }
    }

    // Obtener último ID insertado
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    // Verificar si la tabla existe
    public function tableExists($tableName)
    {
        $sql = "SHOW TABLES LIKE '" . $tableName . "'";
        $result = $this->query($sql);
        return !empty($result);
    }

    // Crear tabla queries si no existe
    public function createQueriesTable()
    {
        if (!$this->tableExists('queries')) {
            $sql = "CREATE TABLE queries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) UNIQUE NOT NULL,
                sql_query TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            return $this->execute($sql);
        }
        return true;
    }
}

// Función helper para obtener instancia de BD
function getDB()
{
    return Database::getInstance();
}
