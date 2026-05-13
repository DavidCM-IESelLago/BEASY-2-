<?php

// 1. EL PORTERO: Valida el token JWT. 
// Si el token no es válido, este archivo corta la ejecución y da error 401.
require_once __DIR__ . '/../middleware/validateToken.php';

use Fintech\Backend\Usuario;

// 2. Identificar qué quiere hacer el usuario (GET o PUT)
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    // 3. Cargar los datos del usuario usando la constante USER_ID que creó el middleware
    $usuario = Usuario::findById(USER_ID);

    if (!$usuario) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
        exit;
    }

    // --- CASO GET: El usuario quiere ver sus datos ---
    if ($metodo === 'GET') {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                'nombre' => $usuario->getNombre(),
                'apellidos' => $usuario->getApellidos(),
                'email' => $usuario->getEmail(),
                'fecha_registro' => $usuario->getFechaRegistro()
            ]
        ]);
    }

    // --- CASO PUT: El usuario quiere actualizar su nombre/apellidos ---
    else if ($metodo === 'PUT') {
        // Leer los datos del cuerpo (Body) de Thunder Client
        $data = json_decode(file_get_contents("php://input"), true);

        // Validar qué campos permitimos cambiar (Seguridad: no dejamos cambiar el email ni el ID)
        if (isset($data['nombre'])) {
            $usuario->setNombre($data['nombre']);
        }
        if (isset($data['apellidos'])) {
            $usuario->setApellidos($data['apellidos']);
        }

        // 4. Guardar los cambios en la Base de Datos
        if ($usuario->update()) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Perfil actualizado correctamente',
                'data' => [
                    'nombre' => $usuario->getNombre(),
                    'apellidos' => $usuario->getApellidos()
                ]
            ]);
        } else {
            throw new Exception("Error al actualizar los datos en la base de datos");
        }
    }

    // Si intentan usar POST o DELETE en este archivo:
    else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
