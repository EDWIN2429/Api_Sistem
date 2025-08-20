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

/**
 * CLASE AUTH - Maneja autenticación y sesiones de administrador
 */
class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    public function login($username, $password)
    {
        try {
            if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
                $this->createSession($username);

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

    public function logout()
    {
        session_destroy();
        return [
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ];
    }

    public function isAuthenticated()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

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

    private function createSession($username)
    {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['session_token'] = $this->generateSessionToken();
    }

    private function generateSessionToken()
    {
        return bin2hex(random_bytes(32));
    }
}

// Manejo de requests HTTP
$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        handlePostRequest($auth);
        break;

    case 'GET':
        handleGetRequest($auth);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Método no permitido'
        ]);
        break;
}

function handlePostRequest($auth)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['action'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no especificada'
        ]);
        return;
    }

    switch ($input['action']) {
        case 'login':
            handleLogin($auth, $input);
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
}

function handleGetRequest($auth)
{
    if (!isset($_GET['action'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Acción no especificada'
        ]);
        return;
    }

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
}

function handleLogin($auth, $input)
{
    if (!isset($input['username']) || !isset($input['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario y contraseña requeridos'
        ]);
        return;
    }

    $result = $auth->login($input['username'], $input['password']);
    echo json_encode($result);
}
