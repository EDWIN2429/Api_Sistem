<?php

/**
 * CONFIGURACIÓN DE SEGURIDAD AVANZADA
 * Centraliza todas las configuraciones de seguridad del sistema
 */

require_once __DIR__ . '/config.php';

// Configuración de rate limiting para prevenir ataques DoS
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_WINDOW', 60);
define('RATE_LIMIT_MAX_REQUESTS', RATE_LIMIT_PER_MINUTE);

// Configuración de validación SQL estricta
define('SQL_VALIDATION_STRICT', true);
define('SQL_MAX_LENGTH', 5000);
define('SQL_ALLOWED_KEYWORDS', ['SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'ORDER', 'BY', 'GROUP', 'HAVING', 'LIMIT', 'OFFSET', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON', 'AS', 'DISTINCT', 'COUNT', 'SUM', 'AVG', 'MAX', 'MIN']);

// Configuración de logging de seguridad
define('SECURITY_LOGGING_ENABLED', true);
define('LOG_RETENTION_DAYS', 30);

// Configuración de timeouts para prevenir ataques de denegación de servicio
define('QUERY_TIMEOUT_SECONDS', MAX_QUERY_EXECUTION_TIME);
define('CONNECTION_TIMEOUT_SECONDS', 10);
define('SESSION_TIMEOUT_SECONDS', 3600);

// Configuración de protección contra ataques comunes
define('XSS_PROTECTION_ENABLED', true);
define('SQL_INJECTION_PROTECTION_ENABLED', true);
define('CSRF_PROTECTION_ENABLED', true);

// Headers de seguridad para proteger contra ataques web
define('SECURITY_HEADERS', [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;"
]);

class SecurityManager
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Valida que una consulta SQL sea segura
     * Solo permite consultas SELECT y bloquea palabras clave peligrosas
     */
    public function validateSQL($query)
    {
        if (!SQL_VALIDATION_STRICT) {
            return true;
        }

        $query = trim($query);
        $queryUpper = strtoupper($query);

        // Solo consultas SELECT están permitidas por seguridad
        if (strpos($queryUpper, 'SELECT') !== 0) {
            return false;
        }

        // Verificar longitud máxima para prevenir ataques
        if (strlen($query) > SQL_MAX_LENGTH) {
            return false;
        }

        // Lista de palabras clave peligrosas que están bloqueadas
        $dangerousKeywords = [
            'UNION',
            'INSERT',
            'UPDATE',
            'DELETE',
            'DROP',
            'CREATE',
            'ALTER',
            'TRUNCATE',
            'EXEC',
            'EXECUTE',
            'PROCEDURE',
            'FUNCTION',
            'TRIGGER',
            'GRANT',
            'REVOKE',
            'INTO',
            'OUTFILE',
            'DUMPFILE',
            'LOAD_FILE',
            'SLEEP',
            'BENCHMARK',
            'WAIT'
        ];

        foreach ($dangerousKeywords as $keyword) {
            if (strpos($queryUpper, $keyword) !== false) {
                return false;
            }
        }

        // Bloquear punto y coma para prevenir múltiples consultas
        $queryWithoutSemicolon = rtrim($query, ';');
        if (preg_match('/[;]/', $queryWithoutSemicolon)) {
            return false;
        }

        return true;
    }

    /**
     * Sanitiza input del usuario para prevenir XSS y SQL injection
     */
    public function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }

        if (is_string($input)) {
            // Protección contra XSS
            if (XSS_PROTECTION_ENABLED) {
                $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            }

            // Protección contra SQL injection
            if (SQL_INJECTION_PROTECTION_ENABLED) {
                $input = str_replace([';', '--', '/*', '*/', 'xp_', 'sp_'], '', $input);
                $input = preg_replace('/\b(DROP|DELETE|INSERT|UPDATE|CREATE|ALTER|TRUNCATE)\b/i', '', $input);
            }

            // Limitar longitud para prevenir ataques de buffer overflow
            if (strlen($input) > 1000) {
                $input = substr($input, 0, 1000);
            }
        }

        return $input;
    }

    /**
     * Registra eventos de seguridad para auditoría y monitoreo
     */
    public function logEvent($eventType, $details, $level = 'INFO')
    {
        if (!SECURITY_LOGGING_ENABLED) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'event_type' => $eventType,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id() ?? 'none'
        ];

        $logFile = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
        $this->cleanupOldLogs();
    }

    /**
     * Implementa rate limiting por IP para prevenir ataques DoS
     */
    public function checkRateLimit($ipAddress)
    {
        if (!RATE_LIMIT_ENABLED) {
            return true;
        }

        $cacheFile = __DIR__ . '/../logs/rate_limit_' . date('Y-m-d-H') . '.log';
        $currentTime = time();
        $windowStart = $currentTime - RATE_LIMIT_WINDOW;

        $requests = [];
        if (file_exists($cacheFile)) {
            $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
        }

        // Filtrar solicitudes dentro de la ventana de tiempo
        $requests = array_filter($requests, function ($req) use ($windowStart) {
            return $req['timestamp'] > $windowStart;
        });

        // Contar solicitudes para esta IP específica
        $ipRequests = array_filter($requests, function ($req) use ($ipAddress) {
            return $req['ip'] === $ipAddress;
        });

        if (count($ipRequests) >= RATE_LIMIT_MAX_REQUESTS) {
            return false;
        }

        $requests[] = [
            'ip' => $ipAddress,
            'timestamp' => $currentTime
        ];

        file_put_contents($cacheFile, json_encode($requests), LOCK_EX);
        return true;
    }

    /**
     * Aplica headers de seguridad HTTP para proteger contra ataques web
     */
    public function applyHeaders()
    {
        foreach (SECURITY_HEADERS as $header => $value) {
            header("{$header}: {$value}");
        }
    }

    /**
     * Valida tokens CSRF para prevenir ataques de falsificación de solicitudes
     */
    public function validateCSRFToken($token)
    {
        if (!CSRF_PROTECTION_ENABLED) {
            return true;
        }

        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Genera tokens CSRF únicos para cada sesión
     */
    public function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Limpia logs antiguos para mantener el sistema eficiente
     */
    private function cleanupOldLogs()
    {
        $logDir = __DIR__ . '/../logs/';
        $cutoffDate = date('Y-m-d', strtotime('-' . LOG_RETENTION_DAYS . ' days'));

        $files = glob($logDir . '*.log');
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                $fileDate = $matches[1];
                if ($fileDate < $cutoffDate) {
                    unlink($file);
                }
            }
        }
    }
}

// Funciones de compatibilidad para mantener compatibilidad con código existente
function checkRateLimit($ipAddress)
{
    return SecurityManager::getInstance()->checkRateLimit($ipAddress);
}
function applySecurityHeaders()
{
    SecurityManager::getInstance()->applyHeaders();
}
function validateCSRFToken($token)
{
    return SecurityManager::getInstance()->validateCSRFToken($token);
}
function generateCSRFToken()
{
    return SecurityManager::getInstance()->generateCSRFToken();
}
function sanitizeInput($input)
{
    return SecurityManager::getInstance()->sanitizeInput($input);
}
function validateSQLQuery($query)
{
    return SecurityManager::getInstance()->validateSQL($query);
}
function logSecurityEvent($eventType, $details, $level = 'INFO')
{
    SecurityManager::getInstance()->logEvent($eventType, $details, $level);
}
