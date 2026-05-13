<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

//Detectar si se ejecuta en consola
define('IS_CLI', php_sapi_name() === 'cli' || defined('STDIN'));

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Configuración de errores según entorno
if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuraciones específicas para web (no CLI)
if (!IS_CLI) {
    // Configuración de CORS
    header("Access-Control-Allow-Origin: " . $_ENV['CORS_ALLOWED_ORIGINS']);
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Content-Type: application/json; charset=UTF-8");

    // Responder a preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}



// Configuración de zona horaria
date_default_timezone_set('Europe/Madrid');

// Definir constantes para acceso rápido
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('JWT_SECRET', $_ENV['JWT_SECRET_KEY']);
define('JWT_EXPIRATION', (int)$_ENV['JWT_EXPIRATION']);