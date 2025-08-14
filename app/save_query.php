<?php

require_once 'config.php';

// Iniciar sesión
session_start();

// Headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class SaveQuery
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Validar si el usuario está autenticado como admin
     */
    private function isAdminAuthenticated()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Validar que la consulta SQL sea de tipo SELECT únicamente
     */
    private function validateSelectQuery($sql)
    {
        // Convertir a minúsculas para validación
        $sqlLower = strtolower(trim($sql));

        // Verificar que comience con SELECT
        if (!preg_match('/^select\s+/i', $sqlLower)) {
            return [
                'valid' => false,
                'message' => 'Solo se permiten consultas SELECT'
            ];
        }

        // Verificar que NO contenga comandos peligrosos
        $dangerousKeywords = [
            'insert',
            'update',
            'delete',
            'drop',
            'create',
            'alter',
            'truncate',
            'replace',
            'grant',
            'revoke',
            'execute'
        ];

        foreach ($dangerousKeywords as $keyword) {
            if (strpos($sqlLower, $keyword) !== false) {
                return [
                    'valid' => false,
                    'message' => 'La consulta contiene comandos no permitidos: ' . strtoupper($keyword)
                ];
            }
        }

        // Verificar que NO contenga múltiples consultas
        if (strpos($sql, ';') !== false) {
            return [
                'valid' => false,
                'message' => 'No se permiten múltiples consultas separadas por punto y coma'
            ];
        }

        return ['valid' => true, 'message' => 'Consulta válida'];
    }

    /**
     * Sanitizar y validar el título de la consulta
     */
    private function validateTitle($title)
    {
        $title = trim($title);

        if (empty($title)) {
            return [
                'valid' => false,
                'message' => 'El título es obligatorio'
            ];
        }

        if (strlen($title) > 255) {
            return [
                'valid' => false,
                'message' => 'El título no puede exceder 255 caracteres'
            ];
        }

        // Verificar que solo contenga caracteres seguros
        if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $title)) {
            return [
                'valid' => false,
                'message' => 'El título solo puede contener letras, números, guiones y espacios'
            ];
        }

        return ['valid' => true, 'title' => $title];
    }

    /**
     * Verificar si el título ya existe
     */
    private function titleExists($title)
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM queries WHERE title = :title";
            $result = $this->db->query($sql, ['title' => $title]);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Guardar nueva consulta en la base de datos
     */
    public function saveQuery($title, $sqlQuery)
    {
        try {
            // Verificar autenticación
            if (!$this->isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acceso denegado. Debe estar autenticado como administrador.',
                    'code' => 'UNAUTHORIZED'
                ];
            }

            // Validar título
            $titleValidation = $this->validateTitle($title);
            if (!$titleValidation['valid']) {
                return [
                    'success' => false,
                    'message' => $titleValidation['message'],
                    'code' => 'INVALID_TITLE'
                ];
            }

            $title = $titleValidation['title'];

            // Verificar si el título ya existe
            if ($this->titleExists($title)) {
                return [
                    'success' => false,
                    'message' => 'Ya existe una consulta con ese título',
                    'code' => 'TITLE_EXISTS'
                ];
            }

            // Validar consulta SQL
            $sqlValidation = $this->validateSelectQuery($sqlQuery);
            if (!$sqlValidation['valid']) {
                return [
                    'success' => false,
                    'message' => $sqlValidation['message'],
                    'code' => 'INVALID_SQL'
                ];
            }

            // Insertar en la base de datos
            $sql = "INSERT INTO queries (title, sql_query) VALUES (:title, :sql_query)";
            $result = $this->db->execute($sql, [
                'title' => $title,
                'sql_query' => $sqlQuery
            ]);

            if ($result) {
                $queryId = $this->db->lastInsertId();
                return [
                    'success' => true,
                    'message' => 'Consulta guardada exitosamente',
                    'data' => [
                        'id' => $queryId,
                        'title' => $title,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al guardar la consulta en la base de datos',
                    'code' => 'DB_ERROR'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage(),
                'code' => 'INTERNAL_ERROR'
            ];
        }
    }
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Solo se acepta POST.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Procesar request
try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode([
            'success' => false,
            'message' => 'Datos JSON inválidos',
            'code' => 'INVALID_JSON'
        ]);
        exit();
    }

    // Verificar campos requeridos
    if (!isset($input['title']) || !isset($input['sql_query'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Los campos "title" y "sql_query" son obligatorios',
            'code' => 'MISSING_FIELDS'
        ]);
        exit();
    }

    // Instanciar clase y guardar consulta
    $saveQuery = new SaveQuery();
    $result = $saveQuery->saveQuery($input['title'], $input['sql_query']);

    // Establecer código de respuesta HTTP apropiado
    if ($result['success']) {
        http_response_code(201); // Created
    } else {
        switch ($result['code']) {
            case 'UNAUTHORIZED':
                http_response_code(401);
                break;
            case 'INVALID_TITLE':
            case 'INVALID_SQL':
            case 'TITLE_EXISTS':
                http_response_code(400);
                break;
            default:
                http_response_code(500);
        }
    }

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'code' => 'INTERNAL_ERROR'
    ]);
}
