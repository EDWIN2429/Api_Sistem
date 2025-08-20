<?php

/**
 * CLASE DATABASE EXTERNAL
 * Maneja conexiones a la base de datos externa (datos reales)
 * Implementa medidas de seguridad para prevenir inyección SQL
 */

class DatabaseExternal
{
    private static $instance = null;
    private $connection = null;
    private $maxConnections;

    private function __construct()
    {
        $this->maxConnections = MAX_CONNECTIONS;
        $this->initializeConnection();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Ejecuta una consulta SQL en la base de datos externa
     * Con validaciones de seguridad y logging completo
     */
    public function executeQuery($query, $params = [])
    {
        require_once __DIR__ . '/security_config.php';
        $security = SecurityManager::getInstance();

        // Validar que la consulta sea segura antes de ejecutarla
        if (!$security->validateSQL($query)) {
            $security->logEvent('SECURITY_VIOLATION', 'Consulta no permitida: ' . $query, 'WARNING');
            throw new Exception("Consulta no permitida por razones de seguridad");
        }

        $startTime = microtime(true);

        try {
            $stmt = $this->connection->prepare($query);

            if (!$stmt) {
                throw new Exception("Error al preparar la consulta");
            }

            $stmt->execute($params);

            // Verificar que no exceda el tiempo límite de ejecución
            $executionTime = microtime(true) - $startTime;
            if ($executionTime > MAX_QUERY_EXECUTION_TIME) {
                $security->logEvent('TIMEOUT_VIOLATION', 'Consulta excedió tiempo límite: ' . $executionTime . 's', 'WARNING');
                throw new Exception("Consulta excedió el tiempo límite de ejecución");
            }

            // Registrar la ejecución exitosa para auditoría
            $security->logEvent('QUERY_EXECUTED', [
                'query' => $query,
                'params' => $params,
                'execution_time' => $executionTime,
                'rows_affected' => $stmt->rowCount()
            ], 'INFO');

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $security->logEvent('QUERY_ERROR', $e->getMessage(), 'ERROR');
            throw new Exception("Error al ejecutar consulta: " . $e->getMessage());
        }
    }

    /**
     * Inicializa la conexión PDO a la base de datos externa
     * Configura timeouts y opciones de seguridad
     */
    private function initializeConnection()
    {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_EXT_HOST . ";port=" . DB_EXT_PORT . ";dbname=" . DB_EXT_NAME . ";charset=" . DB_EXT_CHARSET,
                DB_EXT_USER,
                DB_EXT_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => MAX_QUERY_EXECUTION_TIME,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_EXT_CHARSET,
                    PDO::ATTR_PERSISTENT => false, // No conexiones persistentes por seguridad
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );

            // Configurar timeouts de sesión MySQL
            $this->connection->exec("SET SESSION wait_timeout=" . MAX_QUERY_EXECUTION_TIME);
            $this->connection->exec("SET SESSION interactive_timeout=" . MAX_QUERY_EXECUTION_TIME);
        } catch (PDOException $e) {
            require_once __DIR__ . '/security_config.php';
            $security = SecurityManager::getInstance();
            $security->logEvent('CONNECTION_ERROR', $e->getMessage(), 'ERROR');
            throw new Exception("Error de conexión a base de datos externa: " . $e->getMessage());
        }
    }

    /**
     * Destructor: limpia la conexión al finalizar
     */
    public function __destruct()
    {
        if ($this->connection) {
            $this->connection = null;
        }
    }
}
