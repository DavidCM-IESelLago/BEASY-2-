<?php
// 1. Cargamos configuración y dependencias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    // Validar el Token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    // Si el token falla, error 401 usando el Helper
    if (!$usuarioId) {
        ResponseHelper::error('Token no válido o no proporcionado', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ResponseHelper::error("Método no permitido", 405);
    }

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Saldo total: suma de todas las cuentas activas del usuario
    $stmtSaldo = $pdo->prepare("
        SELECT COALESCE(SUM(saldo), 0) AS saldo_total
        FROM cuentas
        WHERE usuario_id = :usuario_id AND activa = TRUE
    ");
    $stmtSaldo->execute([':usuario_id' => $usuarioId]);
    $saldoTotal = (float) $stmtSaldo->fetchColumn();

    // IDs de cuentas del usuario (para filtrar transacciones)
    $stmtCuentas = $pdo->prepare("
        SELECT id FROM cuentas
        WHERE usuario_id = :usuario_id AND activa = TRUE
    ");
    $stmtCuentas->execute([':usuario_id' => $usuarioId]);
    $idsCuentas = $stmtCuentas->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsCuentas)) {
        ResponseHelper::jsonResponse([
            "status" => "success",
            "usuario_id" => $usuarioId,
            "dashboard" => [
                "saldo_total" => 0,
                "ultimos_movimientos" => [],
                "estadisticas_gastos" => []
            ]
        ]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($idsCuentas), '?'));

    // Últimos 5 movimientos del usuario:
    // Si la cuenta del usuario es origen → gasto (negativo)
    // Si la cuenta del usuario es destino → ingreso (positivo)
    $stmtMovimientos = $pdo->prepare("
        SELECT
            DATE(t.fecha) AS fecha,
            t.descripcion AS concepto,
            t.tipo,
            CASE
                WHEN t.cuenta_origen_id IN ($placeholders) AND t.tipo != 'ingreso' THEN -ABS(t.monto)
                ELSE ABS(t.monto)
            END AS monto
        FROM transacciones t
        WHERE t.cuenta_origen_id IN ($placeholders)
           OR t.cuenta_destino_id IN ($placeholders)
        ORDER BY t.fecha DESC
        LIMIT 5
    ");

    // Los placeholders se repiten 3 veces en la query
    $params = array_merge($idsCuentas, $idsCuentas, $idsCuentas);
    $stmtMovimientos->execute($params);
    $ultimosMovimientos = $stmtMovimientos->fetchAll(PDO::FETCH_ASSOC);

    // Convertir monto a float
    foreach ($ultimosMovimientos as &$mov) {
        $mov['monto'] = (float) $mov['monto'];
    }

    // 5. Estadísticas de gastos del mes actual agrupadas por tipo
    $stmtStats = $pdo->prepare("
        SELECT
            t.tipo AS categoria,
            SUM(ABS(t.monto)) AS total
        FROM transacciones t
        WHERE t.cuenta_origen_id IN ($placeholders)
          AND t.tipo != 'ingreso'
          AND MONTH(t.fecha) = MONTH(CURRENT_DATE())
          AND YEAR(t.fecha) = YEAR(CURRENT_DATE())
        GROUP BY t.tipo
        ORDER BY total DESC
    ");
    $stmtStats->execute($idsCuentas);
    $statsRaw = $stmtStats->fetchAll(PDO::FETCH_ASSOC);

    // Convertir a formato { "categoria": importe }
    $estadisticasGastos = [];
    foreach ($statsRaw as $stat) {
        $estadisticasGastos[ucfirst($stat['categoria'])] = (float) $stat['total'];
    }

    // 6. Respuesta final
    ResponseHelper::jsonResponse([
        "status" => "success",
        "usuario_id" => $usuarioId,
        "dashboard" => [
            "saldo_total" => $saldoTotal,
            "ultimos_movimientos" => $ultimosMovimientos,
            "estadisticas_gastos" => $estadisticasGastos
        ]
    ]);
} catch (\PDOException $e) {
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor", 500);
}
