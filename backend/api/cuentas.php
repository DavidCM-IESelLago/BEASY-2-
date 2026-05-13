<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php'; // <--- El nuevo Helper

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper; // <--- Importante usarlo aquí

try {
    // 1. Extraer Token de la cabecera
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    // 2. Validar con tu AuthController
    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    // Si el token falla, usamos el Helper para dar error 401
    if (!$usuarioId) {
        ResponseHelper::error("No tienes permiso. Token inválido o inexistente.", 401);
    }

    // 3. Lógica del Endpoint 2.3
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $cuentaId = $_GET['id'];

            // ÉXITO: Saldo de una cuenta
            ResponseHelper::jsonResponse([
                "status" => "success",
                "cuenta" => $cuentaId,
                "saldo" => 1250.75,
                "usuario_id" => $usuarioId
            ]);
        } else {
            // ÉXITO: Listado de cuentas
            ResponseHelper::jsonResponse([
                "status" => "success",
                "cuentas" => [
                    ["id" => "ES111", "nombre" => "Nómina", "saldo" => 1250.75],
                    ["id" => "ES222", "nombre" => "Ahorro", "saldo" => 500.00]
                ]
            ]);
        }
    } else {
        ResponseHelper::error("Método no permitido", 405);
    }
} catch (\PDOException $e) {
    // CAPTURA 2.6: Errores de base de datos (Error 500)
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    // CAPTURA 2.6: Otros errores inesperados
    ResponseHelper::error("Error interno del servidor", 500);
}
