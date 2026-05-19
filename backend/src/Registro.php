<?php
require_once __DIR__ . '/../config/config.php';

use Fintech\Backend\Usuario;
use Fintech\Backend\Cuenta;
use Fintech\Backend\ResponseHelper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHelper::error("Método no permitido", 405);
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['dni'], $data['nombre'], $data['apellidos'], $data['email'], $data['password'])) {
    ResponseHelper::error("Faltan datos obligatorios: dni, nombre, apellidos, email, password", 400);
}

$telefono = isset($data['telefono']) ? trim(preg_replace('/\s+/', '', $data['telefono'])) : null;
if ($telefono === '') $telefono = null;

try {
    
    $nuevoUsuario = Usuario::create(
        $data['dni'], $data['email'], $data['password'],
        $data['nombre'], $data['apellidos'], $telefono
    );

    if (!$nuevoUsuario) ResponseHelper::error("No se pudo crear el usuario", 500);

    
    $numeroCuenta = "ES" . str_pad(mt_rand(1, 9999999999), 18, '0', STR_PAD_LEFT);
    $cuenta = Cuenta::create($nuevoUsuario->getId(), $numeroCuenta, 'corriente', 0.0);

    if (!$cuenta) ResponseHelper::error("Usuario creado pero no se pudo crear la cuenta", 500);

    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $numeroTarjeta = '4' . implode('', array_map(fn() => rand(0, 9), range(1, 15)));
    $cvv           = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
    $expiracion    = date('Y-m-d', strtotime('+3 years'));

    $stmt = $pdo->prepare("
        INSERT INTO tarjetas (cuenta_id, numero, cvv, fecha_expiracion, estado)
        VALUES (:cuenta_id, :numero, :cvv, :fecha_expiracion, 'activa')
    ");
    $stmt->execute([
        'cuenta_id'        => $cuenta->getId(),
        'numero'           => $numeroTarjeta,
        'cvv'              => $cvv,
        'fecha_expiracion' => $expiracion
    ]);

    http_response_code(201);
    echo json_encode([
        'status'  => 'success',
        'message' => 'Usuario registrado con cuenta y tarjeta',
        'data'    => [
            'id'      => $nuevoUsuario->getId(),
            'email'   => $nuevoUsuario->getEmail(),
            'cuenta'  => $numeroCuenta,
            'tarjeta' => chunk_split($numeroTarjeta, 4, ' ')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}