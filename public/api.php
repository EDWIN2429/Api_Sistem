<?php

/**
 * SISTEMA DE API - ENDPOINT PÚBLICO
 * Permite ejecutar consultas SQL almacenadas mediante token de autenticación
 */

// Incluir configuración
require_once '../app/config.php';

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

// Validar parámetros requeridos
$apiToken = $_GET['apiToken'] ?? null;
$queryTitle = $_GET['query'] ?? null;

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
    $sql = "SELECT id, title, sql_query, created_at, updated_at FROM queries WHERE title = ? AND active = 1";
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

    // Ejecutar la consulta
    try {
        $data = $db->query($sqlQuery);

        // Respuesta exitosa
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Consulta ejecutada exitosamente',
            'data' => [
                'query_info' => [
                    'id' => $query['id'],
                    'title' => $query['title'],
                    'created_at' => $query['created_at'],
                    'updated_at' => $query['updated_at']
                ],
                'results' => $data,
                'total_rows' => count($data),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ],
            'status_code' => 200
        ]);
    } catch (Exception $e) {
        // Error al ejecutar la consulta
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error al ejecutar consulta',
            'message' => 'La consulta SQL no pudo ser ejecutada',
            'details' => 'Error interno del servidor',
            'status_code' => 500,
            'query_title' => $queryTitle
        ]);

        // Log del error (en producción, no mostrar detalles al usuario)
        error_log("API Error - Query execution failed: " . $e->getMessage());
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

// Usar la función getDB() de config.php
require_once '../app/config.php';
