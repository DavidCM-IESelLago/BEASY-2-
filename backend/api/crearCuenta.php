<?php
// Bloquear cualquier salida HTML de errores PHP para no contaminar el JSON
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
    // 1. Extraer token (igual que transferencia.php)
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

    // 2. Solo POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ResponseHelper::error('Método no permitido', 405);
    }

    // 3. Leer y validar body
    $body     = json_decode(file_get_contents('php://input'), true);
    $tipo     = $body['tipo']     ?? '';
    $deposito = (float)($body['deposito'] ?? 0);

    if (!in_array($tipo, ['corriente', 'ahorros'], true)) {
        ResponseHelper::error('Tipo de cuenta inválido. Valores permitidos: corriente, ahorros.', 400);
    }

    if ($deposito < 0) {
        ResponseHelper::error('El depósito inicial no puede ser negativo.', 400);
    }

    // 4. Conexión (usando el singleton igual que el resto de endpoints)
    $pdo = Database::getInstance()->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 5. Generar IBAN único (sin dependencias externas)
    $numeroCuenta = generarIBANUnico($pdo);

    // 6. Insertar cuenta
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

    // 7. Recuperar cuenta recién creada
    $stmt2 = $pdo->prepare("
        SELECT id, numero_cuenta, saldo, tipo, fecha_creacion
        FROM cuentas
        WHERE id = :id
    ");
    $stmt2->execute([':id' => $cuentaId]);
    $cuenta = $stmt2->fetch(PDO::FETCH_ASSOC);

    // 8. Notificación al usuario
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
        // No interrumpir la creación si falla la notificación
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

// Captura tanto Exception como Error (PHP fatal errors, undefined functions, etc.)
} catch (\Throwable $e) {
    ob_clean();
    // En desarrollo mostramos el mensaje real; en producción sería genérico
    $debug = ($_ENV['APP_ENV'] ?? 'production') === 'development'
        ? $e->getMessage()
        : 'Error interno del servidor';
    ResponseHelper::error($debug, 500);
}

// ── HELPERS ──────────────────────────────────────────────────────────────────

/**
 * Genera un IBAN ES válido y único en la tabla cuentas.
 * No usa bcmath: implementa el módulo 97 con aritmética entera pura.
 */
function generarIBANUnico(PDO $pdo, int $maxIntentos = 20): string
{
    $check = $pdo->prepare("SELECT COUNT(*) FROM cuentas WHERE numero_cuenta = :num");

    for ($i = 0; $i < $maxIntentos; $i++) {
        $banco    = '2100';
        $sucursal = str_pad((string) rand(0, 9999),      4, '0', STR_PAD_LEFT);
        $control  = str_pad((string) rand(0, 99),        2, '0', STR_PAD_LEFT);
        $numCta   = str_pad((string) rand(0, 999999999), 9, '0', STR_PAD_LEFT)
                  . str_pad((string) rand(0, 9),         1, '0', STR_PAD_LEFT);

        $bban        = $banco . $sucursal . $control . $numCta;   // 20 dígitos
        $checkDigits = calcularControlIBAN('ES', $bban);
        $iban        = 'ES' . $checkDigits . $bban;               // 24 chars

        $check->execute([':num' => $iban]);
        if ((int) $check->fetchColumn() === 0) {
            return $iban;
        }
    }

    throw new \RuntimeException('No se pudo generar un IBAN único.');
}

/**
 * Calcula los 2 dígitos de control ISO 13616 sin usar bcmath.
 * Procesa el número grande dígito a dígito (long division mod 97).
 */
function calcularControlIBAN(string $pais, string $bban): string
{
    // Reorganizar: BBAN + código país + '00'
    $rearranged = $bban . $pais . '00';

    // Convertir letras a su valor numérico (A=10 … Z=35)
    $numerico = '';
    foreach (str_split($rearranged) as $char) {
        if (ctype_alpha($char)) {
            $numerico .= (string)(ord(strtoupper($char)) - ord('A') + 10);
        } else {
            $numerico .= $char;
        }
    }

    // Módulo 97 mediante long division (evita bcmath)
    $mod = 0;
    foreach (str_split($numerico) as $digit) {
        $mod = ($mod * 10 + (int)$digit) % 97;
    }

    $checkDigits = 98 - $mod;
    return str_pad((string)$checkDigits, 2, '0', STR_PAD_LEFT);
}
