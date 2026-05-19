<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    $headers = getallheaders();
    $token   = $headers['X-Beasy-Token'] ?? '';
    if (empty($token)) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
    }

    $auth       = new AuthController();
    $usuario_id = $auth->verifyToken($token);
    if (!$usuario_id) ResponseHelper::error("Token inválido", 401);

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET') {
        $stmt = $pdo->prepare("
            SELECT t.id, t.cuenta_id, t.numero, t.cvv, t.fecha_expiracion, t.estado, t.fecha_creacion,
                   c.saldo
            FROM tarjetas t
            JOIN cuentas c ON t.cuenta_id = c.id
            WHERE c.usuario_id = :usuario_id
            ORDER BY t.id DESC
        ");
        $stmt->execute(['usuario_id' => $usuario_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function($t) {
            $num = $t['numero'];
            return [
                'id'             => (int)$t['id'],
                'cuenta_id'      => (int)$t['cuenta_id'],
                'numero'         => $num,
                'numero_oculto'  => '•••• •••• •••• ' . substr($num, -4),
                'cvv'            => $t['cvv'],
                'expiracion'     => date('m/y', strtotime($t['fecha_expiracion'])),
                'estado'         => $t['estado'],
                'saldo'          => number_format((float)$t['saldo'], 2, '.', ','),
                'fecha_creacion' => $t['fecha_creacion'],
            ];
        }, $rows);

        ResponseHelper::jsonResponse(['status' => 'success', 'data' => $data]);
    }

    elseif ($metodo === 'PUT') {
        $input        = json_decode(file_get_contents('php://input'), true);
        $tarjeta_id   = $input['tarjeta_id'] ?? null;
        $nuevo_estado = $input['estado']      ?? null;

        if (!$tarjeta_id || !$nuevo_estado) ResponseHelper::error("Faltan parámetros", 400);
        if (!in_array($nuevo_estado, ['activa', 'bloqueada', 'cancelada'])) ResponseHelper::error("Estado inválido", 400);

        $stmt = $pdo->prepare("
            SELECT t.id, t.numero FROM tarjetas t
            JOIN cuentas c ON t.cuenta_id = c.id
            WHERE t.id = :tarjeta_id AND c.usuario_id = :usuario_id
        ");
        $stmt->execute(['tarjeta_id' => $tarjeta_id, 'usuario_id' => $usuario_id]);
        $tarjeta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tarjeta) ResponseHelper::error("Tarjeta no encontrada", 404);

        $stmt = $pdo->prepare("UPDATE tarjetas SET estado = :estado WHERE id = :id");
        $stmt->execute(['estado' => $nuevo_estado, 'id' => $tarjeta_id]);

        
        try {
            $ultimos4 = substr($tarjeta['numero'], -4);
            $mensajes = [
                'activa'    => "Tu tarjeta terminada en {$ultimos4} ha sido activada.",
                'bloqueada' => "Tu tarjeta terminada en {$ultimos4} ha sido bloqueada por seguridad.",
                'cancelada' => "Tu tarjeta terminada en {$ultimos4} ha sido cancelada.",
            ];
            $nStmt = $pdo->prepare(
                "INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)"
            );
            $nStmt->execute([
                ':uid' => $usuario_id,
                ':msg' => $mensajes[$nuevo_estado] ?? "Estado de tarjeta actualizado.",
            ]);
        } catch (\Exception $e) {
            
        }

        ResponseHelper::jsonResponse(['status' => 'success', 'message' => 'Estado actualizado', 'estado' => $nuevo_estado]);
    }

    else { ResponseHelper::error("Método no permitido", 405); }

} catch (\PDOException $e) {
    ResponseHelper::error("Error de BD: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error: " . $e->getMessage(), 500);
}