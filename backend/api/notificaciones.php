<?php

// 1. Cargamos la configuración global
require_once __DIR__ . '/../config/config.php';

// 2. CARGAMOS LA CLASE FÍSICAMENTE ANTES QUE EL PORTERO
// Esto evita el Fatal Error porque la clase ya estará en memoria cuando validateToken la necesite
require_once __DIR__ . '/../src/AuthController.php'; 

// 3. Ahora sí, llamamos al portero para validar el Token
require_once __DIR__ . '/../middleware/validateToken.php';

// 4. Cargamos el ayudante de respuestas
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\ResponseHelper;

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    // --- CASO GET: Ver notificaciones (SIMULADO "A PELO") ---
    if ($metodo === 'GET') {
        
        // Inventamos los datos usando la constante USER_ID que creó el portero
        $notificacionesSimuladas = [
            [
                "id" => 1,
                "usuario_id" => USER_ID,
                "mensaje" => "Has recibido una transferencia de 15.50€ de Juan Pérez.",
                "leida" => 0,
                "fecha" => "2026-04-06 10:30:00"
            ],
            [
                "id" => 2,
                "usuario_id" => USER_ID,
                "mensaje" => "Tu transferencia de 200.00€ se ha completado.",
                "leida" => 0,
                "fecha" => "2026-04-05 16:45:00"
            ],
            [
                "id" => 3,
                "usuario_id" => USER_ID,
                "mensaje" => "Aviso: Mantenimiento del servidor esta noche.",
                "leida" => 0,
                "fecha" => "2026-04-04 09:00:00"
            ]
        ];

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'cantidad' => count($notificacionesSimuladas),
            'data' => $notificacionesSimuladas
        ], 200);
    }

    // --- CASO PUT: Marcar como leída (SIMULADO "A PELO") ---
    elseif ($metodo === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['notificacion_id'])) {
            ResponseHelper::error("Falta el parámetro 'notificacion_id'", 400);
        }

        $id = $data['notificacion_id'];

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'message' => "Simulación: La notificación $id ha sido marcada como leída correctamente."
        ], 200);
    }
    
    else {
        ResponseHelper::error("Método no permitido. Usa GET o PUT.", 405);
    }

} catch (\Exception $e) {
    ResponseHelper::error("Error del servidor: " . $e->getMessage(), 500);
}