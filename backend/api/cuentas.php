<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php'; 

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper; 

try {
    
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    
    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    
    if (!$usuarioId) {
        ResponseHelper::error("No tienes permiso. Token inválido o inexistente.", 401);
    }

    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ResponseHelper::error('Método no permitido, 405');
    }

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    if (isset($_GET['id'])) {
        $cuentaId = (int) $_GET['id'];

        $stmt = $pdo->prepare("
            SELECT id, numero_cuenta, saldo, tipo, fecha_creacion 
            FROM cuentas
            WHERE id = :id AND usuario_id = :usuario_id AND activa = TRUE
        ");
        $stmt->execute([
            ':id' => $cuentaId,
            ':usuario_id' => $usuarioId
        ]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cuenta) {
            ResponseHelper::error('Cuenta no encontrada', 404);
        }

        
        $stmtTarjeta = $pdo->prepare("
            SELECT numero, fecha_expiracion, estado
            FROM tarjetas
            WHERE cuenta_id = :cuenta_id
            LIMIT 1
        ");
        $stmtTarjeta->execute(['cuenta_id' => $cuentaId]);
        $tarjeta = $stmtTarjeta->fetch(PDO::FETCH_ASSOC);

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'cuenta' => [
                'id' => (int) $cuenta['id'],
                'numero-cuenta' => $cuenta['numero_cuenta'],
                'saldo' => (float) $cuenta['saldo'],
                'tipo' => $cuenta['tipo'],
                'fecha_creacion' => $cuenta['fecha_creacion'],
                'tarjeta' => $tarjeta ?: null
            ]
        ]);
    
    
    } else {
        $stmt = $pdo->prepare("
            SELECT id, numero_cuenta, saldo, tipo, fecha_creacion
            FROM cuentas
            WHERE usuario_id = :usuario_id AND activa = TRUE
            ORDER BY fecha_creacion ASC
        ");
        $stmt->execute(['usuario_id' => $usuarioId]);
        $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        
        foreach ($cuentas as $c) {
            $c['id'] = (int) $c['id'];
            $c['saldo'] = (float) $c['saldo'];
        }

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'total' => count($cuentas),
            'cuentas' => $cuentas
        ]);
    }
        
} catch (\PDOException $e) {
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor", 500);
}
