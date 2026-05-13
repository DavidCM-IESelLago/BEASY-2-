<?php
require_once __DIR__ . '/../config/config.php';

use Fintech\Backend\AuthController;

// 1. Obtener el token de la cabecera Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

// 2. Validar
$auth = new AuthController();
$usuarioId = $auth->verifyToken($token);

if (!$usuarioId) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Token inválido o no proporcionado'
    ]);
    exit; // Importante: detiene el resto del script
}

// 3. Si el token es bueno, creamos una constante para usarla en los endpoints
define('USER_ID', $usuarioId);
