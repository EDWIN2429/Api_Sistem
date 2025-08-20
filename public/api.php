<?php

/**
 * SISTEMA DE API - ENDPOINT PÚBLICO
 * Permite ejecutar consultas SQL almacenadas mediante token de autenticación
 */

require_once '../app/config.php';
require_once '../app/database_external.php';
require_once '../app/security_config.php';

// Aplicar headers de seguridad HTTP
applySecurityHeaders();

// Configurar headers de respuesta para API JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método GET por seguridad
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

// Implementar rate limiting por IP para prevenir abuso
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

// Sanitizar y validar parámetros de entrada
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

// Validar token de API contra el configurado
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
    // Obtener instancia de la base de datos interna
    $db = getDB();

    // Buscar la consulta SQL almacenada por título
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

    // Verificar que sea consulta SELECT por seguridad
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

    // Validación adicional de seguridad usando SecurityManager
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

    try {
        // Ejecutar consulta en base de datos externa (datos reales)
        $dbExternal = DatabaseExternal::getInstance();
        $sanitizedQuery = $security->sanitizeInput($sqlQuery);
        $data = $dbExternal->executeQuery($sanitizedQuery);

        // Respuesta exitosa con datos de la consulta
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
                    'security_validations_passed' => true
                ]
            ],
            'status_code' => 200
        ]);
    } catch (Exception $e) {
        // Error al ejecutar en base de datos externa
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al ejecutar consulta en base de datos externa',
            'message' => 'La consulta SQL no pudo ser ejecutada en la base de datos de datos reales',
            'details' => 'Error interno del servidor',
            'status_code' => 500,
            'query_title' => $queryTitle
        ]);
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
}
