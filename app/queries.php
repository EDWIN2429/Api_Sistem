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

// ========================================
// CLASE PRINCIPAL - QueriesManager
// ========================================
class QueriesManager
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    // ========================================
    // AUTENTICACIÓN
    // ========================================
    private function isAdminAuthenticated()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    // ========================================
    // VALIDACIONES
    // ========================================
    private function validateSelectQuery($sql)
    {
        // Usar el SecurityManager centralizado para validaciones
        require_once __DIR__ . '/security_config.php';
        $security = SecurityManager::getInstance();

        if (!$security->validateSQL($sql)) {
            return [
                'valid' => false,
                'message' => 'La consulta contiene elementos no permitidos por seguridad'
            ];
        }

        return ['valid' => true, 'message' => 'Consulta válida'];
    }

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

    // ========================================
    // OPERACIONES CRUD
    // ========================================
    public function createQuery($title, $sqlQuery, $description = '')
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
            $sql = "INSERT INTO queries (title, description, sql_query) VALUES (:title, :description, :sql_query)";
            $result = $this->db->execute($sql, [
                'title' => $title,
                'description' => $description,
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
                        'description' => $description,
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
                $sql = "SELECT * FROM queries WHERE title LIKE :search1 OR sql_query LIKE :search2 ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
                $params = [
                    'search1' => '%' . $search . '%',
                    'search2' => '%' . $search . '%',
                    'limit' => $limit,
                    'offset' => $offset
                ];

                $countSql = "SELECT COUNT(*) as total FROM queries WHERE title LIKE :search1 OR sql_query LIKE :search2";
                $countResult = $this->db->query($countSql, ['search1' => '%' . $search . '%', 'search2' => '%' . $search . '%']);
                $total = $countResult[0]['total'];
            } else {
                $sql = "SELECT * FROM queries ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
                $params = [
                    'limit' => $limit,
                    'offset' => $offset
                ];

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

    public function updateQuery($id, $title, $sqlQuery, $description = '')
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
            $sql = "UPDATE queries SET title = :title, description = :description, sql_query = :sql_query, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $result = $this->db->execute($sql, [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'sql_query' => $sqlQuery
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Consulta actualizada exitosamente',
                    'data' => [
                        'id' => $id,
                        'title' => $title,
                        'description' => $description,
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

// ========================================
// MANEJO DE REQUESTS HTTP
// ========================================
$queriesManager = new QueriesManager();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest($queriesManager);
        break;

    case 'POST':
        handlePostRequest($queriesManager);
        break;

    case 'PUT':
        handlePutRequest($queriesManager);
        break;

    case 'DELETE':
        handleDeleteRequest($queriesManager);
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

// ========================================
// FUNCIONES AUXILIARES PARA MANEJAR REQUESTS
// ========================================
function handleGetRequest($queriesManager)
{
    if (isset($_GET['id'])) {
        $result = $queriesManager->getQueryById($_GET['id']);
    } else {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $result = $queriesManager->getQueries($page, $limit, $search);
    }

    echo json_encode($result);
}

function handlePostRequest($queriesManager)
{
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

    $description = isset($input['description']) ? $input['description'] : '';
    $result = $queriesManager->createQuery($input['title'], $input['sql_query'], $description);

    if ($result['success']) {
        http_response_code(201);
    } else {
        setErrorResponseCode($result['code']);
    }

    echo json_encode($result);
}

function handlePutRequest($queriesManager)
{
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

    $description = isset($input['description']) ? $input['description'] : '';
    $result = $queriesManager->updateQuery($input['id'], $input['title'], $input['sql_query'], $description);

    if (!$result['success']) {
        setErrorResponseCode($result['code']);
    }

    echo json_encode($result);
}

function handleDeleteRequest($queriesManager)
{
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
        setErrorResponseCode($result['code']);
    }

    echo json_encode($result);
}

function setErrorResponseCode($code)
{
    switch ($code) {
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
