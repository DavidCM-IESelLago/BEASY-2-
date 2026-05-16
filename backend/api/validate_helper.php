<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ResponseHelper::error("Método no permitido", 405);
}

$headers = getallheaders();
$token = $headers['X-Beasy-Token'] ?? '';

if (empty($token)) {
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
}

if (empty($token)) {
    ResponseHelper::error("Token no recibido", 401);
}

$auth = new AuthController();
$usuario_id = $auth->verifyToken($token);

if (!$usuario_id) {
    ResponseHelper::error("Token inválido o caducado", 401);
}

ResponseHelper::jsonResponse([
    "status"  => "success",
    "user_id" => $usuario_id
]);