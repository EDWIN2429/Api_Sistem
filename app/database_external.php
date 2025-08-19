<?php

// ========================================
// CLASE DATABASE EXTERNAL
// ========================================
// Maneja conexiones a la base de datos externa (datos reales)
// Implementa medidas de seguridad críticas para prevenir inyección SQL
// ========================================

class DatabaseExternal
{
    private static $instance = null;
    private $connection = null;
    private $connectionPool = [];
    private $maxConnections;
    private $executionLog = [];

    // ========================================
    // CONSTRUCTOR PRIVADO (Singleton)
    // ========================================
    private function __construct()
    {
        $this->maxConnections = MAX_CONNECTIONS;
        $this->initializeConnectionPool();
    }

    // ========================================
    // PATRÓN SINGLETON
    // ========================================
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ========================================
    // INICIALIZACIÓN DEL POOL DE CONEXIONES
    // ========================================
    private function initializeConnectionPool()
    {
        try {
            // Crear conexión principal
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

            // Configurar timeout de conexión
            $this->connection->exec("SET SESSION wait_timeout=" . MAX_QUERY_EXECUTION_TIME);
            $this->connection->exec("SET SESSION interactive_timeout=" . MAX_QUERY_EXECUTION_TIME);
        } catch (PDOException $e) {
            require_once __DIR__ . '/security_config.php';
            $security = SecurityManager::getInstance();
            $security->logEvent('CONNECTION_ERROR', $e->getMessage(), 'ERROR');
            throw new Exception("Error de conexión a base de datos externa: " . $e->getMessage());
        }
    }

    // ========================================
    // EJECUCIÓN SEGURA DE CONSULTAS
    // ========================================
    public function executeQuery($query, $params = [])
    {
        // 🔒 VALIDACIÓN CRÍTICA DE SEGURIDAD
        require_once __DIR__ . '/security_config.php';
        $security = SecurityManager::getInstance();

        if (!$security->validateSQL($query)) {
            $security->logEvent('SECURITY_VIOLATION', 'Consulta no permitida: ' . $query, 'WARNING');
            throw new Exception("Consulta no permitida por razones de seguridad");
        }

        // 🔒 VALIDACIÓN DE TIEMPO DE EJECUCIÓN
        $startTime = microtime(true);

        try {
            // 🔒 PREPARED STATEMENT OBLIGATORIO
            $stmt = $this->connection->prepare($query);

            if (!$stmt) {
                throw new Exception("Error al preparar la consulta");
            }

            // 🔒 EJECUCIÓN CON PARÁMETROS SANITIZADOS
            $stmt->execute($params);

            // 🔒 VALIDACIÓN DE TIEMPO
            $executionTime = microtime(true) - $startTime;
            if ($executionTime > MAX_QUERY_EXECUTION_TIME) {
                $security->logEvent('TIMEOUT_VIOLATION', 'Consulta excedió tiempo límite: ' . $executionTime . 's', 'WARNING');
                throw new Exception("Consulta excedió el tiempo límite de ejecución");
            }

            // 🔒 LOGGING DE SEGURIDAD
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

    // ========================================
    // ALERTAS DE SEGURIDAD
    // ========================================
    private function sendSecurityAlert($eventType, $details)
    {
        // 🔒 LOGGING DE ALERTA
        $alertFile = __DIR__ . '/../logs/alerts_' . date('Y-m-d') . '.log';
        $alertMessage = "🚨 ALERTA DE SEGURIDAD - " . $eventType . "\n";
        $alertMessage .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $alertMessage .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        $alertMessage .= "Detalles: " . json_encode($details) . "\n";

        if (is_dir(dirname($alertFile))) {
            file_put_contents($alertFile, $alertMessage . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    // ========================================
    // OBTENER ESTADÍSTICAS DE SEGURIDAD
    // ========================================
    public function getSecurityStats()
    {
        return [
            'total_queries' => count($this->executionLog),
            'security_violations' => count(array_filter($this->executionLog, function ($log) {
                return $log['event_type'] === 'SECURITY_VIOLATION';
            })),
            'timeout_violations' => count(array_filter($this->executionLog, function ($log) {
                return $log['event_type'] === 'TIMEOUT_VIOLATION';
            })),
            'recent_events' => array_slice($this->executionLog, -10)
        ];
    }

    // ========================================
    // LIMPIEZA DE RECURSOS
    // ========================================
    public function __destruct()
    {
        if ($this->connection) {
            $this->connection = null;
        }
    }

    // ========================================
    // PREVENCIÓN DE CLONACIÓN
    // ========================================
    private function __clone() {}

    // ========================================
    // PREVENCIÓN DE DESERIALIZACIÓN
    // ========================================
    public function __wakeup() {}
}
