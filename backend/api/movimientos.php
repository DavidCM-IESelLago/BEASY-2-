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

    
    $page  = max(1, (int)($_GET['page']  ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    
    $pdo = Database::getInstance()->getConnection();

    $sql = "
        SELECT 
            t.id,
            t.tipo,
            t.descripcion  AS concepto,
            t.fecha,
            t.monto,
            c_origen.usuario_id  AS origen_usuario_id,
            c_destino.usuario_id AS destino_usuario_id
        FROM transacciones t
        LEFT JOIN cuentas c_origen  ON t.cuenta_origen_id  = c_origen.id
        LEFT JOIN cuentas c_destino ON t.cuenta_destino_id = c_destino.id
        WHERE c_origen.usuario_id = :usuarioId
           OR c_destino.usuario_id = :usuarioId
        ORDER BY t.fecha DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':limit',     $limit,     PDO::PARAM_INT);
    $stmt->bindValue(':offset',    $offset,    PDO::PARAM_INT);
    $stmt->execute();

    $transaccionesDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

$movimientosFinales = [];
foreach ($transaccionesDB as $mov) {
    switch ($mov['tipo']) {
        case 'ingreso':
            
            $cantidad_final = (float)$mov['monto'];
            break;
        case 'compra':
            
            $cantidad_final = -(float)$mov['monto'];
            break;
        default:
            
            $es_ingreso = ((int)$mov['destino_usuario_id'] === $usuarioId);
            $cantidad_final = $es_ingreso ? (float)$mov['monto'] : -(float)$mov['monto'];
            break;
    }

    $movimientosFinales[] = [
        "id"       => $mov['id'],
        "tipo"     => $mov['tipo'],
        "cantidad" => $cantidad_final,
        "concepto" => $mov['concepto'],
        "fecha"    => $mov['fecha']
    ];
}
    ResponseHelper::jsonResponse([
        "status"     => "success",
        "usuario_id" => $usuarioId,
        "paginacion" => [
            "pagina_actual"      => $page,
            "limite_por_pagina"  => $limit
        ],
        "movimientos" => $movimientosFinales
    ]);

} catch (\PDOException $e) {
    ResponseHelper::error("Error en la consulta de movimientos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor: " . $e->getMessage(), 500);
}