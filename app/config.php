<?php

// Función simple para cargar variables del .env
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

// Cargar el archivo .env
try {
    loadEnv(__DIR__ . '/.env');
} catch (Exception $e) {
    // Si no existe .env, usar valores por defecto
    putenv("DB_HOST=localhost");
    putenv("DB_PORT=3307");
    putenv("DB_NAME=api_system");
    putenv("DB_USER=root");
    putenv("DB_PASSWORD=");
    putenv("DB_CHARSET=utf8mb4");
    putenv("API_TOKEN=mi_token_secreto_12345");
    putenv("ADMIN_USERNAME=admin");
    putenv("ADMIN_PASSWORD=admin123");
    putenv("APP_ENV=development");
    putenv("TIMEZONE=America/Mexico_City");
}

// Constantes de la aplicación
define('APP_NAME', getenv('APP_NAME') ?: 'Sistema de API');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');

// Constantes de base de datos
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'api_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Constantes de API
define('API_TOKEN', getenv('API_TOKEN') ?: 'mi_token_secreto_12345');
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

// Constantes de entorno
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Configuración de errores basada en el entorno
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuración de zona horaria
$timezone = getenv('TIMEZONE') ?: 'America/Mexico_City';
date_default_timezone_set($timezone);

// Función helper para obtener variables de entorno
function env($key, $default = null)
{
    return getenv($key) ?: $default;
}

// Función para obtener instancia de la base de datos
function getDB()
{
    require_once __DIR__ . '/database.php';
    return Database::getInstance();
}
