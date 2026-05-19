<?php

ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';
require_once __DIR__ . '/../src/Database.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Database;

try {
    
    $headers    = getallheaders();
    $token      = $headers['X-Beasy-Token'] ?? '';

    if (empty($token)) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token      = str_replace('Bearer ', '', $authHeader);
    }

    $auth      = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    if (!$usuarioId) {
        ResponseHelper::error('No tienes permiso. Token inválido o inexistente.', 401);
    }

    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ResponseHelper::error('Método no permitido', 405);
    }

    
    $body     = json_decode(file_get_contents('php://input'), true);
    $tipo     = $body['tipo']     ?? '';
    $deposito = (float)($body['deposito'] ?? 0);

    if (!in_array($tipo, ['corriente', 'ahorros'], true)) {
        ResponseHelper::error('Tipo de cuenta inválido. Valores permitidos: corriente, ahorros.', 400);
    }

    if ($deposito < 0) {
        ResponseHelper::error('El depósito inicial no puede ser negativo.', 400);
    }

    
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    
    $numeroCuenta = generarIBANUnico($pdo);

    
    $stmt = $pdo->prepare("
        INSERT INTO cuentas (usuario_id, numero_cuenta, saldo, tipo, activa)
        VALUES (:usuario_id, :numero_cuenta, :saldo, :tipo, 1)
    ");
    $stmt->execute([
        ':usuario_id'    => $usuarioId,
        ':numero_cuenta' => $numeroCuenta,
        ':saldo'         => $deposito,
        ':tipo'          => $tipo,
    ]);

    $cuentaId = (int) $pdo->lastInsertId();

    
    $stmt2 = $pdo->prepare("
        SELECT id, numero_cuenta, saldo, tipo, fecha_creacion
        FROM cuentas
        WHERE id = :id
    ");
    $stmt2->execute([':id' => $cuentaId]);
    $cuenta = $stmt2->fetch(PDO::FETCH_ASSOC);

    
    try {
        $tipoTexto = $tipo === 'ahorros' ? 'de ahorros' : 'corriente';
        $nStmt = $pdo->prepare(
            "INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)"
        );
        $nStmt->execute([
            ':uid' => $usuarioId,
            ':msg' => "¡Tu nueva cuenta {$tipoTexto} ha sido creada! IBAN: {$numeroCuenta}",
        ]);
    } catch (\Exception $e) {
        
    }

    ResponseHelper::jsonResponse([
        'status' => 'success',
        'cuenta' => [
            'id'             => (int)   $cuenta['id'],
            'numero_cuenta'  =>         $cuenta['numero_cuenta'],
            'saldo'          => (float) $cuenta['saldo'],
            'tipo'           =>         $cuenta['tipo'],
            'fecha_creacion' =>         $cuenta['fecha_creacion'],
        ],
    ]);

} catch (\Throwable $e) {
    ob_clean();
    
    $debug = ($_ENV['APP_ENV'] ?? 'production') === 'development'
        ? $e->getMessage()
        : 'Error interno del servidor';
    ResponseHelper::error($debug, 500);
}

function generarIBANUnico(PDO $pdo, int $maxIntentos = 20): string
{
    $check = $pdo->prepare("SELECT COUNT(*) FROM cuentas WHERE numero_cuenta = :num");

    for ($i = 0; $i < $maxIntentos; $i++) {
        $banco    = '2100';
        $sucursal = str_pad((string) rand(0, 9999),      4, '0', STR_PAD_LEFT);
        $control  = str_pad((string) rand(0, 99),        2, '0', STR_PAD_LEFT);
        $numCta   = str_pad((string) rand(0, 999999999), 9, '0', STR_PAD_LEFT)
                  . str_pad((string) rand(0, 9),         1, '0', STR_PAD_LEFT);

        $bban        = $banco . $sucursal . $control . $numCta;   
        $checkDigits = calcularControlIBAN('ES', $bban);
        $iban        = 'ES' . $checkDigits . $bban;               

        $check->execute([':num' => $iban]);
        if ((int) $check->fetchColumn() === 0) {
            return $iban;
        }
    }

    throw new \RuntimeException('No se pudo generar un IBAN único.');
}

function calcularControlIBAN(string $pais, string $bban): string
{
    
    $rearranged = $bban . $pais . '00';

    
    $numerico = '';
    foreach (str_split($rearranged) as $char) {
        if (ctype_alpha($char)) {
            $numerico .= (string)(ord(strtoupper($char)) - ord('A') + 10);
        } else {
            $numerico .= $char;
        }
    }

    
    $mod = 0;
    foreach (str_split($numerico) as $digit) {
        $mod = ($mod * 10 + (int)$digit) % 97;
    }

    $checkDigits = 98 - $mod;
    return str_pad((string)$checkDigits, 2, '0', STR_PAD_LEFT);
}
