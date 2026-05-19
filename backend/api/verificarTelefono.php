<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';
require_once __DIR__ . '/../src/Database.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Database;

try {
    
    $headers = getallheaders();
    $token = $headers['X-Beasy-Token'] ?? '';

    if (empty($token)) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
    }

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    if (!$usuarioId) {
        ResponseHelper::error("Token inválido o no proporcionado", 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ResponseHelper::error("Método no permitido", 405);
    }

    
    $telefono = trim($_GET['telefono'] ?? '');
    $telefonoLimpio = preg_replace('/\s+/', '', $telefono);

    if (empty($telefonoLimpio)) {
        ResponseHelper::error("Debes indicar un teléfono", 400);
    }

    
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, u.apellidos
        FROM usuarios u
        WHERE REPLACE(u.telefono, ' ', '') = :tel AND u.activo = 1
        LIMIT 1
    ");
    $stmt->execute([':tel' => $telefonoLimpio]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        ResponseHelper::jsonResponse([
            'status' => 'success',
            'existe' => false
        ]);
    }

    
    $stmtC = $pdo->prepare("
        SELECT id FROM cuentas
        WHERE usuario_id = :uid AND activa = 1
        ORDER BY fecha_creacion ASC
        LIMIT 1
    ");
    $stmtC->execute([':uid' => $usuario['id']]);
    $cuenta = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        ResponseHelper::jsonResponse([
            'status' => 'success',
            'existe' => false
        ]);
    }

    ResponseHelper::jsonResponse([
        'status'     => 'success',
        'existe'     => true,
        'nombre'     => $usuario['nombre'] . ' ' . $usuario['apellidos'],
        'es_propio'  => ((int) $usuario['id'] === (int) $usuarioId)
    ]);

} catch (\PDOException $e) {
    ResponseHelper::error("Error en la consulta: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor: " . $e->getMessage(), 500);
}
