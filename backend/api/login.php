<?php
require_once __DIR__ . '/../config/config.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    // 1. Solo permitimos POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ResponseHelper::error("Método no permitido", 405);
    }

    // 2. Leer datos del cuerpo (JSON)
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // 3. Validar campos vacíos (Seguridad básica)
    if (empty($email) || empty($password)) {
        ResponseHelper::error("Email y contraseña son obligatorios", 400);
    }

    // 4. Intentar login con tu AuthController
    $auth = new AuthController();
    $token = $auth->login($email, $password);

    // 5. Respuesta de Éxito con el Helper
    ResponseHelper::jsonResponse([
        'status' => 'success',
        'message' => 'Login correcto',
        'token' => $token
    ]);
} catch (\PDOException $e) {
    // Error 500 si falla la base de datos al buscar al usuario
    ResponseHelper::error("Error de conexión: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    // Error 401 si las credenciales fallan (viene del throw de tu AuthController)
    ResponseHelper::error($e->getMessage(), 401);
}
