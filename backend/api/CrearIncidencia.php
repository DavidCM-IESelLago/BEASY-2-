<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';
require_once __DIR__ . '/../src/Database.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Database;

try {
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
        ResponseHelper::error("No tienes permiso. Token inválido o inexistente.", 401);
    }

    
    $input = json_decode(file_get_contents('php://input'), true);

    $tipo        = trim($input['tipo']    ?? '');
    $descripcion = trim($input['mensaje'] ?? '');

    if (empty($tipo) || empty($descripcion)) {
        ResponseHelper::error("Datos incompletos: tipo y descripción son obligatorios", 400);
    }

    
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        INSERT INTO incidencias (usuario_id, tipo, descripcion)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$usuario_id, $tipo, $descripcion]);

    $idGenerado = $db->lastInsertId();

    ResponseHelper::jsonResponse([
        "status" => "success",
        "ticket" => "BE-" . str_pad($idGenerado, 5, "0", STR_PAD_LEFT)
    ]);

} catch (\PDOException $e) {
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno: " . $e->getMessage(), 500);
}