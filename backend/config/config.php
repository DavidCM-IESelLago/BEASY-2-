<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

define('IS_CLI', php_sapi_name() === 'cli' || defined('STDIN'));

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (!IS_CLI) {
    
    header("Access-Control-Allow-Origin: " . ($_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:8081'));
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Beasy-Token");
    header("Content-Type: application/json; charset=UTF-8");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

date_default_timezone_set('Europe/Madrid');

define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);

define('JWT_SECRET', $_ENV['JWT_SECRET_KEY']); 
define('JWT_EXPIRATION', (int)$_ENV['JWT_EXPIRATION']);