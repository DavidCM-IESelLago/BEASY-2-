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

    
    $iban = trim($_GET['iban'] ?? '');
    
    $ibanLimpio = preg_replace('/\s+/', '', $iban);

    if (empty($ibanLimpio)) {
        ResponseHelper::error("Debes indicar un IBAN", 400);
    }

    
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT id, numero_cuenta, usuario_id, activa
        FROM cuentas
        WHERE REPLACE(numero_cuenta, ' ', '') = :iban
        LIMIT 1
    ");
    $stmt->execute([':iban' => $ibanLimpio]);
    $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta || $cuenta['activa'] != 1) {
        ResponseHelper::jsonResponse([
            'status' => 'success',
            'existe' => false
        ]);
    }

    ResponseHelper::jsonResponse([
        'status' => 'success',
        'existe' => true,
        'cuenta_id' => (int) $cuenta['id'],
        'numero_cuenta' => $cuenta['numero_cuenta'],
        'es_propia' => ((int) $cuenta['usuario_id'] === (int) $usuarioId)
    ]);

} catch (\PDOException $e) {
    ResponseHelper::error("Error en la consulta: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor: " . $e->getMessage(), 500);
}
