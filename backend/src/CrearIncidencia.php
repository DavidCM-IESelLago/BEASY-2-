<?php
// backend/api/CrearIncidencia.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/Database.php'; 
require_once __DIR__ . '/Incidencia.php';

use Fintech\Backend\AuthController;

header('Content-Type: application/json');

// 1. Obtener el Token de las cabeceras
$auth = new AuthController();
$headers = getallheaders();
$token = $headers['X-Beasy-Token'] ?? str_replace('Bearer ', '', $headers['Authorization'] ?? '');
// 2. Validar el usuario (Esto sustituye a la sesión)
$usuario_id = $auth->verifyToken($token);

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión inválida o expirada']);
    exit;
}

// 3. Recoger datos
$tipo = $_POST['tipo'] ?? 'General';
$asunto = $_POST['asunto'] ?? 'Reporte de Seguridad';
$mensaje = $_POST['mensaje'] ?? '';

// 4. Manejo del archivo (Nombre 'archivo' igual que en el JS)
$ruta_final = null;
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
    $nombre_archivo = time() . "_" . basename($_FILES['archivo']['name']);
    $directorio = "../uploads/"; 
    
    if (!is_dir($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $ruta_dest = $directorio . $nombre_archivo;
    
    if (move_uploaded_file($_FILES['archivo']['tmp_name'], $ruta_dest)) {
        $ruta_final = $nombre_archivo;
    }
}

// 5. Crear incidencia
try {
    $ticket = Incidencia::crear($usuario_id, $tipo, $asunto, $mensaje, $ruta_final);
    echo json_encode(['status' => 'success', 'ticket' => $ticket]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}