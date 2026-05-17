<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

try {
    // 1. Validar token
    $headers = getallheaders();
    $token   = $headers['X-Beasy-Token'] ?? '';
    if (empty($token)) {
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = str_replace('Bearer ', '', $authHeader);
    }

    $auth       = new AuthController();
    $usuario_id = $auth->verifyToken($token);
    if (!$usuario_id) ResponseHelper::error("Token inválido", 401);

    // 2. Verificar que es admin
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = :id");
    $stmt->execute(['id' => $usuario_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['rol'] !== 'admin') {
        ResponseHelper::error("Acceso denegado", 404);
    }

    $accion = $_GET['accion'] ?? $_SERVER['REQUEST_METHOD'];

    // ── GET ?accion=datos ─────────────────────────────────────────
    // Devuelve usuarios + cuentas + tarjetas
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'datos') {

        $usuarios = $pdo->query("
            SELECT id, nombre, apellidos, email, dni, rol, activo,
                   DATE_FORMAT(fecha_registro, '%d/%m/%Y') AS fecha_registro,
                   DATE_FORMAT(ultimo_acceso, '%d/%m/%Y %H:%i') AS ultimo_acceso
            FROM usuarios ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $cuentas = $pdo->query("
            SELECT c.id, c.usuario_id, u.nombre, u.apellidos,
                   c.numero_cuenta, c.saldo, c.tipo, c.activa,
                   DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y') AS fecha_creacion
            FROM cuentas c JOIN usuarios u ON c.usuario_id = u.id
            ORDER BY c.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $tarjetas = $pdo->query("
            SELECT t.id, t.cuenta_id, c.numero_cuenta,
                   u.nombre, u.apellidos,
                   t.numero, t.cvv, t.estado,
                   DATE_FORMAT(t.fecha_expiracion, '%m/%y') AS expiracion,
                   DATE_FORMAT(t.fecha_creacion, '%d/%m/%Y') AS fecha_creacion
            FROM tarjetas t
            JOIN cuentas c ON t.cuenta_id = c.id
            JOIN usuarios u ON c.usuario_id = u.id
            ORDER BY t.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        ResponseHelper::jsonResponse([
            'status'   => 'success',
            'usuarios' => $usuarios,
            'cuentas'  => $cuentas,
            'tarjetas' => $tarjetas,
        ]);
    }

    // ── GET ?accion=incidencias ───────────────────────────────────
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'incidencias') {

        $incidencias = $pdo->query("
            SELECT i.id, i.tipo, i.descripcion, i.estado,
                   DATE_FORMAT(i.fecha_creacion, '%d/%m/%Y') AS fecha_incidencia,
                   DATE_FORMAT(i.fecha_creacion, '%d/%m/%Y %H:%i') AS fecha_creacion,
                   u.nombre, u.apellidos, u.email
            FROM incidencias i
            JOIN usuarios u ON i.usuario_id = u.id
            ORDER BY i.estado ASC, i.fecha_creacion DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        ResponseHelper::jsonResponse(['status' => 'success', 'incidencias' => $incidencias]);
    }

    // ── PUT — Cerrar incidencia ───────────────────────────────────
    elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $incidencia_id = $input['incidencia_id'] ?? null;

        if (!$incidencia_id) ResponseHelper::error("Falta incidencia_id", 400);

        $stmt = $pdo->prepare("UPDATE incidencias SET estado = 'resuelta' WHERE id = :id");
        $stmt->execute(['id' => $incidencia_id]);

        ResponseHelper::jsonResponse(['status' => 'success', 'message' => 'Incidencia cerrada']);
    }

    else {
        ResponseHelper::error("Acción no válida", 400);
    }

} catch (\PDOException $e) {
    ResponseHelper::error("Error de BD: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error("Error: " . $e->getMessage(), 500);
}
