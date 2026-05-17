<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Usuario.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Usuario;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ResponseHelper::error("Método no permitido", 405);
    }

    $data     = json_decode(file_get_contents("php://input"), true);
    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        ResponseHelper::error("Email y contraseña son obligatorios", 400);
    }

    $auth  = new AuthController();
    $token = $auth->login($email, $password);

    // Obtener rol para redirigir en el frontend
    $usuario = Usuario::findByEmail($email);
    $rol     = $usuario ? $usuario->getRol() : 'usuario';

    ResponseHelper::jsonResponse([
        'status'  => 'success',
        'message' => 'Login correcto',
        'token'   => $token,
        'rol'     => $rol
    ]);

} catch (\PDOException $e) {
    ResponseHelper::error("Error de conexión: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    // Credenciales incorrectas → 400 (no 401, que se reserva para token expirado)
    ResponseHelper::error($e->getMessage(), 400);
}