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

    // 3. Lógica del Endpoint
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ResponseHelper::error('Método no permitido, 405');
    }

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cuenta específica por ID
    if (isset($_GET['id'])) {
        $cuentaId = (int) $_GET['id'];

        $stmt = $pdo->prepare("
            SELECT id, numero_cuenta, saldo, tipo, fecha_creacion 
            FROM cuentas
            WHERE id = :id AND usuario_id = :usuario_id AND activa = TRUE
        ");
        $stmt->execute([
            ':id' => $cuentaId,
            ':usuario_id' => $usuarioId
        ]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cuenta) {
            ResponseHelper::error('Cuenta no encontrada', 404);
        }

        // Tarjeta asociada a la cuenta si existe
        $stmtTarjeta = $pdo->prepare("
            SELECT numero, fecha_expiracion, estado
            FROM tarjetas
            WHERE cuenta_id = :cuenta_id
            LIMIT 1
        ");
        $stmtTarjeta->execute(['cuenta_id' => $cuentaId]);
        $tarjeta = $stmtTarjeta->fetch(PDO::FETCH_ASSOC);

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'cuenta' => [
                'id' => (int) $cuenta['id'],
                'numero-cuenta' => $cuenta['numero_cuenta'],
                'saldo' => (float) $cuenta['saldo'],
                'tipo' => $cuenta['tipo'],
                'fecha_creacion' => $cuenta['fecha_creacion'],
                'tarjeta' => $tarjeta ?: null
            ]
        ]);
    
    // Listado de todas las cuentas del usuario
    } else {
        $stmt = $pdo->prepare("
            SELECT id, numero_cuenta, saldo, tipo, fecha_creacion
            FROM cuentas
            WHERE usuario_id = :usuario_id AND activa = TRUE
            ORDER BY fecha_creacion ASC
        ");
        $stmt->execute(['usuario_id' => $usuarioId]);
        $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // COnvertir tipos
        foreach ($cuentas as $c) {
            $c['id'] = (int) $c['id'];
            $c['saldo'] = (float) $c['saldo'];
        }

        ResponseHelper::jsonResponse([
            'status' => 'success',
            'total' => count($cuentas),
            'cuentas' => $cuentas
        ]);
    }
        /* 
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
        }*/
} catch (\PDOException $e) {
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error interno del servidor", 500);
}
