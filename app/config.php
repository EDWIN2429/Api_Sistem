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
define('APP_NAME', getenv('APP_NAME'));
define('APP_VERSION', getenv('APP_VERSION'));

// ========================================
// CONSTANTES DE BASE DE DATOS
// ========================================
define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_CHARSET', getenv('DB_CHARSET'));

// ========================================
// CONSTANTES DE BASE DE DATOS EXTERNA
// ========================================
define('DB_EXT_HOST', getenv('DB_EXT_HOST'));
define('DB_EXT_PORT', getenv('DB_EXT_PORT'));
define('DB_EXT_NAME', getenv('DB_EXT_NAME'));
define('DB_EXT_USER', getenv('DB_EXT_USER'));
define('DB_EXT_PASSWORD', getenv('DB_EXT_PASSWORD'));
define('DB_EXT_CHARSET', getenv('DB_EXT_CHARSET'));

// ========================================
// CONSTANTES DE SEGURIDAD
// ========================================
define('MAX_QUERY_EXECUTION_TIME', getenv('MAX_QUERY_EXECUTION_TIME'));
define('MAX_CONNECTIONS', getenv('MAX_CONNECTIONS'));
define('RATE_LIMIT_PER_MINUTE', getenv('RATE_LIMIT_PER_MINUTE'));

// ========================================
// CONSTANTES DE API
// ========================================
define('API_TOKEN', getenv('API_TOKEN'));
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD'));

// ========================================
// CONSTANTES DE ENTORNO
// ========================================
define('APP_ENV', getenv('APP_ENV'));

// ========================================
// CONFIGURACIÓN DE ERRORES
// ========================================
configureErrorReporting();

// ========================================
// CONFIGURACIÓN DE ZONA HORARIA
// ========================================
$timezone = getenv('TIMEZONE');
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
