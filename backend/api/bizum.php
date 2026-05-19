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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ResponseHelper::error("Método no permitido", 405);
    }

    
    $input = json_decode(file_get_contents('php://input'), true);
    $telefonoDestino = trim($input['telefono'] ?? '');
    $importe         = isset($input['importe']) ? (float) $input['importe'] : 0;
    $concepto        = trim($input['concepto'] ?? '');

    
    if (empty($telefonoDestino)) {
        ResponseHelper::error("El teléfono del destinatario es obligatorio", 400);
    }
    if ($importe <= 0) {
        ResponseHelper::error("El importe debe ser mayor que 0", 400);
    }
    if (empty($concepto)) {
        ResponseHelper::error("El concepto es obligatorio", 400);
    }

    $telefonoLimpio = preg_replace('/\s+/', '', $telefonoDestino);

    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    $stmtU = $pdo->prepare("
        SELECT id, nombre, apellidos
        FROM usuarios
        WHERE REPLACE(telefono, ' ', '') = :tel AND activo = 1
        LIMIT 1
    ");
    $stmtU->execute([':tel' => $telefonoLimpio]);
    $usuarioDestino = $stmtU->fetch(PDO::FETCH_ASSOC);

    if (!$usuarioDestino) {
        ResponseHelper::error("Ese número no corresponde a ninguna cuenta", 404);
    }

    if ((int) $usuarioDestino['id'] === (int) $usuarioId) {
        ResponseHelper::error("No puedes enviarte un Bizum a ti mismo", 400);
    }

    
    $stmtO = $pdo->prepare("
        SELECT id, numero_cuenta, saldo, usuario_id
        FROM cuentas
        WHERE usuario_id = :uid AND activa = 1
        ORDER BY fecha_creacion ASC
        LIMIT 1
    ");
    $stmtO->execute([':uid' => $usuarioId]);
    $cuentaOrigen = $stmtO->fetch(PDO::FETCH_ASSOC);

    if (!$cuentaOrigen) {
        ResponseHelper::error("No tienes ninguna cuenta activa para enviar Bizum", 400);
    }

    
    $stmtD = $pdo->prepare("
        SELECT id, numero_cuenta, saldo, usuario_id
        FROM cuentas
        WHERE usuario_id = :uid AND activa = 1
        ORDER BY fecha_creacion ASC
        LIMIT 1
    ");
    $stmtD->execute([':uid' => $usuarioDestino['id']]);
    $cuentaDestino = $stmtD->fetch(PDO::FETCH_ASSOC);

    if (!$cuentaDestino) {
        ResponseHelper::error("El destinatario no tiene ninguna cuenta activa", 404);
    }

    
    if ((float) $cuentaOrigen['saldo'] < $importe) {
        ResponseHelper::error("Saldo insuficiente para realizar el Bizum", 400);
    }

    
    $pdo->beginTransaction();
    try {
        $u1 = $pdo->prepare("UPDATE cuentas SET saldo = saldo - :m WHERE id = :id");
        $u1->execute([':m' => $importe, ':id' => $cuentaOrigen['id']]);

        $u2 = $pdo->prepare("UPDATE cuentas SET saldo = saldo + :m WHERE id = :id");
        $u2->execute([':m' => $importe, ':id' => $cuentaDestino['id']]);

        $ins = $pdo->prepare("
            INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, fecha)
            VALUES (:o, :d, :m, 'bizum', :desc, NOW())
        ");
        $ins->execute([
            ':o'    => $cuentaOrigen['id'],
            ':d'    => $cuentaDestino['id'],
            ':m'    => $importe,
            ':desc' => $concepto
        ]);

        $transaccionId = (int) $pdo->lastInsertId();

        
        try {
            $nO = $pdo->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)");
            $nO->execute([
                ':uid' => $cuentaOrigen['usuario_id'],
                ':msg' => "Has enviado un Bizum de " . number_format($importe, 2, ',', '.') . " € a " . $usuarioDestino['nombre']
            ]);

            $nD = $pdo->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)");
            $nD->execute([
                ':uid' => $cuentaDestino['usuario_id'],
                ':msg' => "Has recibido un Bizum de " . number_format($importe, 2, ',', '.') . " €"
            ]);
        } catch (\Exception $e) {
            
        }

        $pdo->commit();

        ResponseHelper::jsonResponse([
            'status'           => 'success',
            'message'          => 'Bizum realizado con éxito',
            'transaccion_id'   => $transaccionId,
            'destinatario'     => $usuarioDestino['nombre'] . ' ' . $usuarioDestino['apellidos'],
            'cuenta_origen'    => $cuentaOrigen['numero_cuenta'],
            'monto'            => $importe,
            'concepto'         => $concepto,
            'nuevo_saldo'      => (float) $cuentaOrigen['saldo'] - $importe
        ]);

    } catch (\Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (\PDOException $e) {
    ResponseHelper::error("Error en la base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor: " . $e->getMessage(), 500);
}
