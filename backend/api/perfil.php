<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';
require_once __DIR__ . '/../src/Usuario.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Usuario;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ResponseHelper::error("Método no permitido", 405);
    }

    
    $headers = getallheaders();
    $token = $headers['X-Beasy-Token'] ?? '';

    if (empty($token)) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
    }

    $auth = new AuthController();
    $usuario_id = $auth->verifyToken($token);

    if (!$usuario_id) {
        ResponseHelper::error("Token inválido o inexistente.", 401);
    }

    $usuario = Usuario::findById($usuario_id);

    if (!$usuario) {
        ResponseHelper::error("Usuario no encontrado.", 404);
    }

    ResponseHelper::jsonResponse([
        "status"    => "success",
        "id"        => $usuario->getId(),
        "nombre"    => $usuario->getNombre(),
        "apellidos" => $usuario->getApellidos(),
        "email"     => $usuario->getEmail(),
        "iniciales" => mb_strtoupper(
            mb_substr($usuario->getNombre(), 0, 1) .
            mb_substr($usuario->getApellidos(), 0, 1)
        )
    ]);

} catch (\PDOException $e) {
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno: " . $e->getMessage(), 500);
}
