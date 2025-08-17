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
        throw new Exception("El archivo .env no existe en la ruta especificada.");
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
}

// Cargar archivo .env o usar valores por defecto
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    setDefaultEnvironment();
}

// ========================================
// VALORES POR DEFECTO
// ========================================
function setDefaultEnvironment()
{
    $defaults = [
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3307',
        'DB_NAME' => 'api_system',
        'DB_USER' => 'root',
        'DB_PASSWORD' => '',
        'DB_CHARSET' => 'utf8mb4',
        'API_TOKEN' => 'mi_token_secreto_12345',
        'ADMIN_USERNAME' => 'admin',
        'ADMIN_PASSWORD' => 'admin123',
        'APP_ENV' => 'development',
        'TIMEZONE' => 'America/Mexico_City'
    ];

    foreach ($defaults as $key => $value) {
        putenv("{$key}={$value}");
    }
}

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
