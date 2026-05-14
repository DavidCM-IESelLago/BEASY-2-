<?php
// 1. Cargamos configuración y dependencias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    // 2. Extraer y Validar el Token
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    // Si el token falla, error 401 usando el Helper
    if (!$usuarioId) {
        ResponseHelper::error('Token no válido o no proporcionado', 401);
    }

    // 3. Lógica del Dashboard
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // --- Datos simulados (Aquí irían tus consultas SQL) ---

        $saldoTotal = 6500.25;

        $ultimosMovimientos = [
            ["fecha" => "2024-03-28", "concepto" => "Suscripción Netflix", "monto" => -15.99],
            ["fecha" => "2024-03-27", "concepto" => "Transferencia Recibida", "monto" => 200.00],
            ["fecha" => "2024-03-26", "concepto" => "Cena Restaurante", "monto" => -45.50],
            ["fecha" => "2024-03-25", "concepto" => "Compra Amazon", "monto" => -120.00],
            ["fecha" => "2024-03-24", "concepto" => "Gasolinera", "monto" => -60.00]
        ];

        $graficoGastos = [
            "Ocio" => 61.49,
            "Vivienda" => 450.00,
            "Transporte" => 60.00,
            "Compras" => 120.00
        ];

        // 4. Respuesta de Éxito con el Helper
        ResponseHelper::jsonResponse([
            "status" => "success",
            "usuario_id" => $usuarioId,
            "dashboard" => [
                "saldo_total" => $saldoTotal,
                "ultimos_movimientos" => $ultimosMovimientos,
                "estadisticas_gastos" => $graficoGastos
            ]
        ]);
    } else {
        ResponseHelper::error("Método no permitido", 405);
    }
} catch (\PDOException $e) {
    // Captura errores de DB (Punto 2.6)
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    // Captura cualquier otro error
    ResponseHelper::error("Error interno del servidor", 500);
}
