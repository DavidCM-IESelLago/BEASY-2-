<?php
// Configuración y dependencias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    // Extraer y Validar el Token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    if (!$usuarioId) {
        ResponseHelper::error("Token inválido o no proporcionado", 401);
    }

    // 3. Gestión de Paginación (Parámetros GET)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

    if ($page < 1) $page = 1;
    if ($limit < 1) $limit = 10;

    // Cálculo para el SQL (OFFSET)
    $offset = ($page - 1) * $limit;

    // 4. Lógica del Endpoint 2.4
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Simulación de datos (Aquí iría tu SELECT ... LIMIT $limit OFFSET $offset)
        $movimientosSimulados = [
            ["id" => 101, "tipo" => "ingreso", "cantidad" => 500.00, "concepto" => "Nómina Marzo", "fecha" => "2024-03-25"],
            ["id" => 102, "tipo" => "gasto", "cantidad" => -20.50, "concepto" => "Supermercado", "fecha" => "2024-03-26"]
        ];

        // Respuesta de Éxito
        ResponseHelper::jsonResponse([
            "status" => "success",
            "usuario_id" => $usuarioId,
            "paginacion" => [
                "pagina_actual" => $page,
                "limite_por_pagina" => $limit,
                "total_registros_simulados" => 2
            ],
            "movimientos" => $movimientosSimulados
        ]);
    } else {
        ResponseHelper::error("Método no permitido", 405);
    }
} catch (\PDOException $e) {
    // Captura de errores de base de datos (Punto 2.6)
    ResponseHelper::error("Error en la consulta de movimientos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    // Captura de cualquier otro error
    ResponseHelper::error("Error interno del servidor", 500);
}
