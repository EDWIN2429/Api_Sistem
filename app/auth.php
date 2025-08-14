<?php

require_once 'config.php';

// Iniciar sesión
session_start();

// Headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Autenticar usuario administrador
     */
    public function login($username, $password)
    {
        try {
            // Validar credenciales
            if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                // Crear sesión
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['login_time'] = time();

                return [
                    'success' => true,
                    'message' => 'Login exitoso',
                    'user' => $username,
                    'token' => $this->generateSessionToken()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Credenciales inválidas'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error en autenticación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        session_destroy();
        return [
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ];
    }

    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Obtener información de la sesión actual
     */
    public function getSessionInfo()
    {
        if (!$this->isAuthenticated()) {
            return [
                'success' => false,
                'message' => 'No autenticado'
            ];
        }

        return [
            'success' => true,
            'user' => $_SESSION['admin_username'],
            'login_time' => $_SESSION['login_time'],
            'session_duration' => time() - $_SESSION['login_time']
        ];
    }

    /**
     * Generar token de sesión único
     */
    private function generateSessionToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validar token de sesión
     */
    public function validateSessionToken($token)
    {
        // Implementar validación de token si es necesario
        return $this->isAuthenticated();
    }
}

// Instanciar clase de autenticación
$auth = new Auth();

// Manejar diferentes tipos de requests
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'login':
                    if (isset($input['username']) && isset($input['password'])) {
                        $result = $auth->login($input['username'], $input['password']);
                        echo json_encode($result);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Usuario y contraseña requeridos'
                        ]);
                    }
                    break;

                case 'logout':
                    $result = $auth->logout();
                    echo json_encode($result);
                    break;

                default:
                    echo json_encode([
                        'success' => false,
                        'message' => 'Acción no válida'
                    ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Acción no especificada'
            ]);
        }
        break;

    case 'GET':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'check':
                    $result = $auth->getSessionInfo();
                    echo json_encode($result);
                    break;

                default:
                    echo json_encode([
                        'success' => false,
                        'message' => 'Acción no válida'
                    ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Acción no especificada'
            ]);
        }
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}
