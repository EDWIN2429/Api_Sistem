<?php

require_once 'config.php';

// Iniciar sesión
session_start();

// Headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class QueriesManager
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Verificar si el usuario está autenticado como admin
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
     * Validar título de la consulta
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

        if (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $title)) {
            return [
                'valid' => false,
                'message' => 'El título solo puede contener letras, números, guiones y espacios'
            ];
        }

        return ['valid' => true, 'title' => $title];
    }

    /**
     * Verificar si el título ya existe (excluyendo el ID actual para updates)
     */
    private function titleExists($title, $excludeId = null)
    {
        try {
            if ($excludeId) {
                $sql = "SELECT COUNT(*) as count FROM queries WHERE title = :title AND id != :id";
                $result = $this->db->query($sql, ['title' => $title, 'id' => $excludeId]);
            } else {
                $sql = "SELECT COUNT(*) as count FROM queries WHERE title = :title";
                $result = $this->db->query($sql, ['title' => $title]);
            }
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * CREATE - Crear nueva consulta
     */
    public function createQuery($title, $sqlQuery)
    {
        try {
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
                    'message' => 'Consulta creada exitosamente',
                    'data' => [
                        'id' => $queryId,
                        'title' => $title,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al crear la consulta en la base de datos',
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

    /**
     * READ - Obtener consultas (con paginación opcional)
     */
    public function getQueries($page = 1, $limit = 10, $search = '')
    {
        try {
            if (!$this->isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acceso denegado. Debe estar autenticado como administrador.',
                    'code' => 'UNAUTHORIZED'
                ];
            }

            $offset = ($page - 1) * $limit;

            // Construir consulta con búsqueda opcional
            if (!empty($search)) {
                $sql = "SELECT * FROM queries WHERE title LIKE :search OR sql_query LIKE :search ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
                $params = [
                    'search' => '%' . $search . '%',
                    'limit' => $limit,
                    'offset' => $offset
                ];

                // Contar total de resultados para paginación
                $countSql = "SELECT COUNT(*) as total FROM queries WHERE title LIKE :search OR sql_query LIKE :search";
                $countResult = $this->db->query($countSql, ['search' => '%' . $search . '%']);
                $total = $countResult[0]['total'];
            } else {
                $sql = "SELECT * FROM queries ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
                $params = [
                    'limit' => $limit,
                    'offset' => $offset
                ];

                // Contar total de resultados para paginación
                $countResult = $this->db->query("SELECT COUNT(*) as total FROM queries");
                $total = $countResult[0]['total'];
            }

            $queries = $this->db->query($sql, $params);

            return [
                'success' => true,
                'data' => $queries,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener consultas: ' . $e->getMessage(),
                'code' => 'DB_ERROR'
            ];
        }
    }

    /**
     * READ - Obtener consulta por ID
     */
    public function getQueryById($id)
    {
        try {
            if (!$this->isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acceso denegado. Debe estar autenticado como administrador.',
                    'code' => 'UNAUTHORIZED'
                ];
            }

            $sql = "SELECT * FROM queries WHERE id = :id";
            $result = $this->db->query($sql, ['id' => $id]);

            if (empty($result)) {
                return [
                    'success' => false,
                    'message' => 'Consulta no encontrada',
                    'code' => 'NOT_FOUND'
                ];
            }

            return [
                'success' => true,
                'data' => $result[0]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener consulta: ' . $e->getMessage(),
                'code' => 'DB_ERROR'
            ];
        }
    }

    /**
     * UPDATE - Actualizar consulta existente
     */
    public function updateQuery($id, $title, $sqlQuery)
    {
        try {
            if (!$this->isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acceso denegado. Debe estar autenticado como administrador.',
                    'code' => 'UNAUTHORIZED'
                ];
            }

            // Verificar que la consulta existe
            $existingQuery = $this->getQueryById($id);
            if (!$existingQuery['success']) {
                return $existingQuery;
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

            // Verificar si el título ya existe (excluyendo el ID actual)
            if ($this->titleExists($title, $id)) {
                return [
                    'success' => false,
                    'message' => 'Ya existe otra consulta con ese título',
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

            // Actualizar en la base de datos
            $sql = "UPDATE queries SET title = :title, sql_query = :sql_query, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $result = $this->db->execute($sql, [
                'id' => $id,
                'title' => $title,
                'sql_query' => $sqlQuery
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Consulta actualizada exitosamente',
                    'data' => [
                        'id' => $id,
                        'title' => $title,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al actualizar la consulta en la base de datos',
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

    /**
     * DELETE - Eliminar consulta
     */
    public function deleteQuery($id)
    {
        try {
            if (!$this->isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acceso denegado. Debe estar autenticado como administrador.',
                    'code' => 'UNAUTHORIZED'
                ];
            }

            // Verificar que la consulta existe
            $existingQuery = $this->getQueryById($id);
            if (!$existingQuery['success']) {
                return $existingQuery;
            }

            // Eliminar de la base de datos
            $sql = "DELETE FROM queries WHERE id = :id";
            $result = $this->db->execute($sql, ['id' => $id]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Consulta eliminada exitosamente',
                    'data' => [
                        'id' => $id,
                        'deleted_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al eliminar la consulta de la base de datos',
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

// Instanciar clase
$queriesManager = new QueriesManager();

// Manejar diferentes tipos de requests
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Obtener consulta específica por ID
            $result = $queriesManager->getQueryById($_GET['id']);
        } else {
            // Obtener lista de consultas con paginación opcional
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $search = isset($_GET['search']) ? $_GET['search'] : '';

            $result = $queriesManager->getQueries($page, $limit, $search);
        }
        echo json_encode($result);
        break;

    case 'POST':
        // Crear nueva consulta
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['title']) || !isset($input['sql_query'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Los campos "title" y "sql_query" son obligatorios',
                'code' => 'MISSING_FIELDS'
            ]);
            exit();
        }

        $result = $queriesManager->createQuery($input['title'], $input['sql_query']);

        if ($result['success']) {
            http_response_code(201);
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
        break;

    case 'PUT':
        // Actualizar consulta existente
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['id']) || !isset($input['title']) || !isset($input['sql_query'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Los campos "id", "title" y "sql_query" son obligatorios',
                'code' => 'MISSING_FIELDS'
            ]);
            exit();
        }

        $result = $queriesManager->updateQuery($input['id'], $input['title'], $input['sql_query']);

        if (!$result['success']) {
            switch ($result['code']) {
                case 'UNAUTHORIZED':
                    http_response_code(401);
                    break;
                case 'NOT_FOUND':
                    http_response_code(404);
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
        break;

    case 'DELETE':
        // Eliminar consulta
        if (isset($_GET['id'])) {
            $result = $queriesManager->deleteQuery($_GET['id']);
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id'])) {
                $result = $queriesManager->deleteQuery($input['id']);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID de consulta requerido',
                    'code' => 'MISSING_ID'
                ]);
                exit();
            }
        }

        if (!$result['success']) {
            switch ($result['code']) {
                case 'UNAUTHORIZED':
                    http_response_code(401);
                    break;
                case 'NOT_FOUND':
                    http_response_code(404);
                    break;
                default:
                    http_response_code(500);
            }
        }

        echo json_encode($result);
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido',
            'code' => 'METHOD_NOT_ALLOWED'
        ]);
        break;
}
