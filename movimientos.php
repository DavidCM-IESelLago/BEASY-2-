<?php
// Configuración y dependencias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    // 1. Extraer y Validar el Token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    if (!$usuarioId) {
        ResponseHelper::error("Token inválido o no proporcionado", 401);
    }

    // 2. Gestión de Paginación (Parámetros GET)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 10;

    $offset = ($page - 1) * $limit;

    // 3. Lógica del Endpoint
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // NOTA: Asegúrate de que $pdo esté disponible. Si tu config.php no crea la variable $pdo, 
        // instánciala aquí (ej: $pdo = new PDO(...);)
        global $pdo; 

        // Consulta SQL real: Buscamos transacciones donde el usuario sea el origen O el destino
        $sql = "
            SELECT 
                t.id, 
                t.tipo, 
                t.descripcion as concepto, 
                t.fecha, 
                t.monto,
                c_origen.usuario_id as origen_usuario_id,
                c_destino.usuario_id as destino_usuario_id
            FROM transacciones t
            LEFT JOIN cuentas c_origen ON t.cuenta_origen_id = c_origen.id
            LEFT JOIN cuentas c_destino ON t.cuenta_destino_id = c_destino.id
            WHERE c_origen.usuario_id = :usuarioId OR c_destino.usuario_id = :usuarioId
            ORDER BY t.fecha DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':usuarioId', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $transaccionesDB = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Formatear los datos para el Frontend
        $movimientosFinales = [];

        foreach ($transaccionesDB as $mov) {
            // Lógica matemática: Si el usuario es el destino, el dinero ENTRA (positivo).
            // Si el usuario es el origen, el dinero SALE (negativo).
            $es_ingreso = ($mov['destino_usuario_id'] == $usuarioId);
            $cantidad_final = $es_ingreso ? (float)$mov['monto'] : -(float)$mov['monto'];

            $movimientosFinales[] = [
                "id" => $mov['id'],
                "tipo" => $mov['tipo'], // Aquí vendrá 'ingreso', 'compra', 'transferencia' o 'bizum'
                "cantidad" => $cantidad_final,
                "concepto" => $mov['concepto'],
                "fecha" => $mov['fecha']
            ];
        }

        // Respuesta de Éxito
        ResponseHelper::jsonResponse([
            "status" => "success",
            "usuario_id" => $usuarioId,
            "paginacion" => [
                "pagina_actual" => $page,
                "limite_por_pagina" => $limit
            ],
            "movimientos" => $movimientosFinales
        ]);

    } else {
        ResponseHelper::error("Método no permitido", 405);
    }

} catch (\PDOException $e) {
    ResponseHelper::error("Error en la consulta de movimientos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor", 500);
}