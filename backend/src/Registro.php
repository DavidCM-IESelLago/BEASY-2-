<?php

// 1. Cargar configuración y clases
require_once __DIR__ . '/../config/config.php';

use Fintech\Backend\Usuario;
use Fintech\Backend\Cuenta;
use Fintech\Backend\ResponseHelper;

// 2. Cabeceras obligatorias (Ya configuradas en config.php, pero aseguramos JSON)
header('Content-Type: application/json');

// 3. Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error("Método no permitido", 405);
}

// 4. Leer los datos del cuerpo de la petición (JSON)
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// 5. Validar que vengan los datos necesarios
if (!isset($data['dni'], $data['nombre'], $data['apellidos'], $data['email'], $data['password'])) {
    ResponseHelper::error("Faltan datos obligatorios: dni, nombre, apellidos, email, password", 400);
}

try {
    // 6. Crear el usuario (Punto 2.1)
    // El modelo Usuario::create ya gestiona el hash de la contraseña y si el email existe
    $nuevoUsuario = Usuario::create(
        $data['dni'],
        $data['email'],
        $data['password'],
        $data['nombre'],
        $data['apellidos']
    );

    if ($nuevoUsuario) {
        // 7. Crear cuenta corriente por defecto (Requisito 2.1)
        // Generamos un IBAN ficticio simple: ES + 20 dígitos aleatorios
        $numeroCuenta = "ES" . str_pad(mt_rand(1, 9999999999), 18, '0', STR_PAD_LEFT);
        
        Cuenta::create(
            $nuevoUsuario->getId(),
            $numeroCuenta,
            'corriente',
            0.0 // Saldo inicial 0
        );

        // 8. Respuesta de éxito
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Usuario registrado correctamente con cuenta asociada',
            'data' => [
                'id' => $nuevoUsuario->getId(),
                'email' => $nuevoUsuario->getEmail(),
                'cuenta' => $numeroCuenta
            ]
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}