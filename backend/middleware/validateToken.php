<?php
// backend/middleware/validateToken.php
ob_start(); 
header('Content-Type: application/json');

// Desactivar errores para que no ensucien el JSON
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../src/AuthController.php';

    $headers = getallheaders();
    
    // 1. BUSCAR PRIMERO NUESTRA CABECERA PERSONALIZADA (La que no borra el hosting)
    $token = $headers['X-Beasy-Token'] ?? ''; 

    // 2. Si no está, buscar en las estándar por si acaso
    if (empty($token)) {
        $authHeader = $headers['Authorization'] ?? $headers['X-Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
    }

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token no recibido']);
        exit;
    }

    $auth = new \Fintech\Backend\AuthController();
    $usuario_id = $auth->verifyToken($token);

    if ($usuario_id) {
        echo json_encode(['status' => 'success', 'user_id' => $usuario_id]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Token invalido']);
    }

} catch (\Throwable $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(), // Esto te dirá si falta "Usuario" o "JWT"
        'trace' => $e->getTraceAsString()
    ]);
}
ob_end_flush();
exit;