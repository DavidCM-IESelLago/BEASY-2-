<?php

// 1. Cargamos la configuración global
require_once __DIR__ . '/../config/config.php';

// 2. Cargamos la clase de Auth antes que el portero
require_once __DIR__ . '/../src/AuthController.php'; 

// 3. Llamamos al portero para validar el Token
require_once __DIR__ . '/../middleware/validateToken.php';

// 4. Cargamos el ayudante de respuestas
require_once __DIR__ . '/../src/ResponseHelper.php';

// ¡AQUÍ ESTABA LA CLAVE! Usamos el namespace correcto:
use Fintech\Backend\ResponseHelper;

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    // --- CASO GET: Listar tarjetas (SIMULADO "A PELO") ---
    if ($metodo === 'GET') {
        $tarjetasSimuladas = [
            [
                "id" => 101,
                "numero" => "4532 8765 1234 5678",
                "cvv" => "123",
                "expiracion" => "04/29", // 3 años vista desde 2026
                "estado" => "activa"
            ]
        ];

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'data' => $tarjetasSimuladas
        ], 200);
    }

    // --- CASO POST: Generar tarjeta (SIMULADO "A PELO") ---
    elseif ($metodo === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['cuenta_id'])) {
            ResponseHelper::error("Falta 'cuenta_id'", 400);
        }

        // Generación aleatoria simulada
        $nuevoNumero = "4532 " . rand(1000, 9999) . " " . rand(1000, 9999) . " " . rand(1000, 9999);
        $nuevoCvv = (string)rand(100, 999);
        $nuevaExp = date('m') . "/29";

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'message' => 'Tarjeta virtual generada',
            'data' => [
                "id" => rand(200, 300),
                "numero" => $nuevoNumero,
                "cvv" => $nuevoCvv,
                "expiracion" => $nuevaExp,
                "estado" => "activa"
            ]
        ], 201);
    }

    // --- CASO PUT: Bloquear (SIMULADO "A PELO") ---
    elseif ($metodo === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['tarjeta_id']) || !isset($data['estado'])) {
            ResponseHelper::error("Faltan parámetros", 400);
        }

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'message' => "Tarjeta " . $data['tarjeta_id'] . " actualizada a " . $data['estado']
        ], 200);
    }

    else {
        ResponseHelper::error("Método no permitido", 405);
    }

} catch (\Exception $e) {
    ResponseHelper::error("Error: " . $e->getMessage(), 500);
}