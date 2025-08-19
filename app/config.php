<?php

// ========================================
// CONFIGURACIÓN DEL SISTEMA
// ========================================

// ========================================
// CARGA DE VARIABLES DE ENTORNO
// ========================================
function loadEnv($path)
{
    if (!file_exists($path)) {
        return false; // Archivo no existe
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') === false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
        }
    }
    return true; // Archivo cargado exitosamente
}

// Cargar archivo .env si existe
loadEnv(__DIR__ . '/.env');

// ========================================
// CONSTANTES DE LA APLICACIÓN
// ========================================
define('APP_NAME', getenv('APP_NAME') ?: 'Sistema de API');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');

// ========================================
// CONSTANTES DE BASE DE DATOS
// ========================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'api_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ========================================
// CONSTANTES DE BASE DE DATOS EXTERNA
// ========================================
define('DB_EXT_HOST', getenv('DB_EXT_HOST') ?: 'localhost');
define('DB_EXT_PORT', getenv('DB_EXT_PORT') ?: '3307');
define('DB_EXT_NAME', getenv('DB_EXT_NAME') ?: 'datos_reales');
define('DB_EXT_USER', getenv('DB_EXT_USER') ?: 'usuario_externo');
define('DB_EXT_PASSWORD', getenv('DB_EXT_PASSWORD') ?: 'password_seguro_123');
define('DB_EXT_CHARSET', getenv('DB_EXT_CHARSET') ?: 'utf8mb4');

// ========================================
// CONSTANTES DE SEGURIDAD
// ========================================
define('MAX_QUERY_EXECUTION_TIME', getenv('MAX_QUERY_EXECUTION_TIME') ?: '30');
define('MAX_CONNECTIONS', getenv('MAX_CONNECTIONS') ?: '10');
define('RATE_LIMIT_PER_MINUTE', getenv('RATE_LIMIT_PER_MINUTE') ?: '100');

// ========================================
// CONSTANTES DE API
// ========================================
define('API_TOKEN', getenv('API_TOKEN') ?: 'mi_token_secreto_12345');
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

// ========================================
// CONSTANTES DE ENTORNO
// ========================================
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// ========================================
// CONFIGURACIÓN DE ERRORES
// ========================================
configureErrorReporting();

// ========================================
// CONFIGURACIÓN DE ZONA HORARIA
// ========================================
$timezone = getenv('TIMEZONE') ?: 'America/Mexico_City';
date_default_timezone_set($timezone);

// ========================================
// FUNCIONES AUXILIARES
// ========================================
function env($key, $default = null)
{
    return getenv($key) ?: $default;
}

function getDB()
{
    require_once __DIR__ . '/database.php';
    return Database::getInstance();
}

function configureErrorReporting()
{
    if (APP_ENV === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
}
