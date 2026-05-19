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

    $cuentaOrigenId = isset($input['cuenta_origen_id']) ? (int) $input['cuenta_origen_id'] : 0;
    $ibanDestino    = trim($input['iban_destino'] ?? '');
    $importe        = isset($input['importe']) ? (float) $input['importe'] : 0;
    $concepto       = trim($input['concepto'] ?? '');

    
    if ($cuentaOrigenId <= 0) {
        ResponseHelper::error("Debes seleccionar una cuenta de origen", 400);
    }
    if (empty($ibanDestino)) {
        ResponseHelper::error("El IBAN de destino es obligatorio", 400);
    }
    if ($importe <= 0) {
        ResponseHelper::error("El importe debe ser mayor que 0", 400);
    }
    if (empty($concepto)) {
        ResponseHelper::error("El concepto es obligatorio", 400);
    }

    $ibanLimpio = preg_replace('/\s+/', '', $ibanDestino);

    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    $stmtOrigen = $pdo->prepare("
        SELECT id, numero_cuenta, saldo, usuario_id, activa
        FROM cuentas
        WHERE id = :id AND usuario_id = :uid
        LIMIT 1
    ");
    $stmtOrigen->execute([
        ':id'  => $cuentaOrigenId,
        ':uid' => $usuarioId
    ]);
    $cuentaOrigen = $stmtOrigen->fetch(PDO::FETCH_ASSOC);

    if (!$cuentaOrigen) {
        ResponseHelper::error("La cuenta de origen no te pertenece o no existe", 403);
    }
    if ((int) $cuentaOrigen['activa'] !== 1) {
        ResponseHelper::error("La cuenta de origen está inactiva", 400);
    }

    
    $stmtDest = $pdo->prepare("
        SELECT id, numero_cuenta, saldo, usuario_id, activa
        FROM cuentas
        WHERE REPLACE(numero_cuenta, ' ', '') = :iban
        LIMIT 1
    ");
    $stmtDest->execute([':iban' => $ibanLimpio]);
    $cuentaDestino = $stmtDest->fetch(PDO::FETCH_ASSOC);

    if (!$cuentaDestino || (int) $cuentaDestino['activa'] !== 1) {
        ResponseHelper::error("La cuenta de destino no existe", 404);
    }

    
    if ((int) $cuentaDestino['id'] === (int) $cuentaOrigen['id']) {
        ResponseHelper::error("No puedes transferir a la misma cuenta de origen", 400);
    }

    
    if ((float) $cuentaOrigen['saldo'] < $importe) {
        ResponseHelper::error("Saldo insuficiente para realizar la transferencia", 400);
    }

    
    $pdo->beginTransaction();
    try {
        
        $upd1 = $pdo->prepare("UPDATE cuentas SET saldo = saldo - :monto WHERE id = :id");
        $upd1->execute([
            ':monto' => $importe,
            ':id'    => $cuentaOrigen['id']
        ]);

        
        $upd2 = $pdo->prepare("UPDATE cuentas SET saldo = saldo + :monto WHERE id = :id");
        $upd2->execute([
            ':monto' => $importe,
            ':id'    => $cuentaDestino['id']
        ]);

        
        $ins = $pdo->prepare("
            INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, fecha)
            VALUES (:origen, :destino, :monto, 'transferencia', :desc, NOW())
        ");
        $ins->execute([
            ':origen'  => $cuentaOrigen['id'],
            ':destino' => $cuentaDestino['id'],
            ':monto'   => $importe,
            ':desc'    => $concepto
        ]);

        $transaccionId = (int) $pdo->lastInsertId();

        
        try {
            $nOrigen = $pdo->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)");
            $nOrigen->execute([
                ':uid' => $cuentaOrigen['usuario_id'],
                ':msg' => "Has transferido " . number_format($importe, 2, ',', '.') . " € a la cuenta " . $cuentaDestino['numero_cuenta']
            ]);

            $nDestino = $pdo->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)");
            $nDestino->execute([
                ':uid' => $cuentaDestino['usuario_id'],
                ':msg' => "Has recibido " . number_format($importe, 2, ',', '.') . " € desde la cuenta " . $cuentaOrigen['numero_cuenta']
            ]);
        } catch (\Exception $e) {
            
        }

        $pdo->commit();

        ResponseHelper::jsonResponse([
            'status'         => 'success',
            'message'        => 'Transferencia realizada con éxito',
            'transaccion_id' => $transaccionId,
            'cuenta_origen'  => $cuentaOrigen['numero_cuenta'],
            'cuenta_destino' => $cuentaDestino['numero_cuenta'],
            'monto'          => $importe,
            'concepto'       => $concepto,
            'nuevo_saldo'    => (float) $cuentaOrigen['saldo'] - $importe
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
