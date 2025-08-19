<?php

/**
 * SISTEMA DE API - ENDPOINT PÚBLICO
 * Permite ejecutar consultas SQL almacenadas mediante token de autenticación
 */

// Incluir configuración
require_once '../app/config.php';
require_once '../app/database_external.php';
require_once '../app/security_config.php';

// 🔒 APLICAR HEADERS DE SEGURIDAD
applySecurityHeaders();

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido',
        'message' => 'Solo se permite el método GET',
        'allowed_methods' => ['GET']
    ]);
    exit();
}

// 🔒 VALIDAR RATE LIMITING
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIP)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit excedido',
        'message' => 'Demasiadas solicitudes. Intente nuevamente en ' . RATE_LIMIT_WINDOW . ' segundos',
        'status_code' => 429,
        'retry_after' => RATE_LIMIT_WINDOW
    ]);
    exit();
}

// 🔒 SANITIZAR PARÁMETROS DE ENTRADA
$apiToken = sanitizeInput($_GET['apiToken'] ?? null);
$queryTitle = sanitizeInput($_GET['query'] ?? null);

if (!$apiToken || !$queryTitle) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Parámetros faltantes',
        'message' => 'Se requieren los parámetros apiToken y query',
        'required_params' => [
            'apiToken' => 'Token de autenticación de la API',
            'query' => 'Título de la consulta a ejecutar'
        ],
        'example_url' => '?apiToken=TOKEN&query=TituloDeLaConsulta'
    ]);
    exit();
}

// Validar token de API
if ($apiToken !== API_TOKEN) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Token inválido',
        'message' => 'El token de API proporcionado no es válido',
        'status_code' => 401
    ]);
    exit();
}

try {
    // Obtener instancia de la base de datos
    $db = getDB();

    // Buscar la consulta por título
    $sql = "SELECT id, title, sql_query, created_at, updated_at FROM queries WHERE title = ?";
    $params = [$queryTitle];

    $result = $db->query($sql, $params);

    if (empty($result)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Consulta no encontrada',
            'message' => "No se encontró una consulta activa con el título: {$queryTitle}",
            'status_code' => 404,
            'searched_title' => $queryTitle
        ]);
        exit();
    }

    $query = $result[0];
    $sqlQuery = $query['sql_query'];

    // Validar que sea consulta SELECT
    if (!preg_match('/^SELECT\s+/i', trim($sqlQuery))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Tipo de consulta no permitido',
            'message' => 'Solo se permiten consultas SELECT por seguridad',
            'status_code' => 400,
            'query_type' => 'No SELECT'
        ]);
        exit();
    }

    // 🔒 VALIDACIÓN CRÍTICA DE SEGURIDAD ADICIONAL
    $security = SecurityManager::getInstance();
    if (!$security->validateSQL($sqlQuery)) {
        $security->logEvent('SECURITY_VIOLATION', 'Consulta bloqueada: ' . $sqlQuery, 'WARNING');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Consulta bloqueada por seguridad',
            'message' => 'La consulta contiene elementos no permitidos',
            'status_code' => 403,
            'query_title' => $queryTitle
        ]);
        exit();
    }

    // 🔒 EJECUTAR CONSULTA EN BASE DE DATOS EXTERNA
    try {
        // Obtener instancia de BD externa
        $dbExternal = DatabaseExternal::getInstance();

        // 🔒 SANITIZAR PARÁMETROS ANTES DE EJECUTAR
        $sanitizedQuery = $security->sanitizeInput($sqlQuery);

        // 🔒 EJECUTAR CONSULTA EN BD EXTERNA
        $data = $dbExternal->executeQuery($sanitizedQuery);

        // 🔒 OBTENER ESTADÍSTICAS DE SEGURIDAD
        $securityStats = $dbExternal->getSecurityStats();

        // Respuesta exitosa
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Consulta ejecutada exitosamente en base de datos externa',
            'data' => [
                'query_info' => [
                    'id' => $query['id'],
                    'title' => $query['title'],
                    'created_at' => $query['created_at'],
                    'updated_at' => $query['updated_at']
                ],
                'results' => $data,
                'total_rows' => count($data),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'security_info' => [
                    'executed_in' => 'external_database',
                    'security_validations_passed' => true,
                    'total_security_checks' => $securityStats['total_queries']
                ]
            ],
            'status_code' => 200
        ]);

        // 🔒 LOGGING DE EJECUCIÓN EXITOSA
        logSuccessfulExecution($queryTitle, $securityStats);
    } catch (Exception $e) {
        // Error al ejecutar la consulta en BD externa
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al ejecutar consulta en base de datos externa',
            'message' => 'La consulta SQL no pudo ser ejecutada en la base de datos de datos reales',
            'details' => 'Error interno del servidor',
            'status_code' => 500,
            'query_title' => $queryTitle
        ]);

        // 🔒 LOGGING DE ERROR DE SEGURIDAD
        logSecurityError($queryTitle, $e->getMessage());
    }
} catch (Exception $e) {
    // Error general del sistema
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor',
        'message' => 'Error interno del servidor',
        'status_code' => 500
    ]);

    // Log del error
    error_log("API Error - System error: " . $e->getMessage());
}

// ========================================
// FUNCIONES DE VALIDACIÓN Y LOGGING
// ========================================

// Función eliminada - Usar SecurityManager::validateSQL() en su lugar

/**
 * 🔒 LOGGING DE EJECUCIÓN EXITOSA
 */
function logSuccessfulExecution($queryTitle, $securityStats)
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => 'SECURITY_ERROR',
        'query_title' => $queryTitle,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'security_stats' => $securityStats
    ];

    $logFile = __DIR__ . '/../logs/api_success_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * 🔒 LOGGING DE ERRORES DE SEGURIDAD
 */
function logSecurityError($queryTitle, $errorMessage)
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => 'SECURITY_ERROR',
        'query_title' => $queryTitle,
        'error_message' => $errorMessage,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    $logFile = __DIR__ . '/../logs/api_errors_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

    // 🔒 LOGGING DE ERROR AL ERROR LOG DEL SISTEMA
    error_log("API Security Error - Query: {$queryTitle}, Error: {$errorMessage}");
}
