<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Notificacion.php';
require_once __DIR__ . '/../middleware/validateToken.php';

use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Notificacion;

$usuarioId = validateToken();
$metodo    = $_SERVER['REQUEST_METHOD'];

try {
    // GET — obtener notificaciones del usuario autenticado
    if ($metodo === 'GET') {
        $notificaciones = Notificacion::findByUsuarioId($usuarioId);

        $data = array_map(fn($n) => [
            'id'         => $n->getId(),
            'usuario_id' => $n->getUsuarioId(),
            'mensaje'    => $n->getMensaje(),
            'leida'      => $n->isLeida(),
            'fecha'      => $n->getFecha(),
        ], $notificaciones);

        ResponseHelper::jsonResponse([
            'status'   => 'success',
            'cantidad' => count($data),
            'data'     => $data,
        ]);
    }

    // PUT — marcar notificación/es como leídas
    elseif ($metodo === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true);

        // Marcar TODAS las no leídas del usuario de una vez
        if (!empty($body['marcar_todas'])) {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "UPDATE notificaciones SET leida = 1 WHERE usuario_id = :uid AND leida = 0"
            );
            $stmt->execute(['uid' => $usuarioId]);

            ResponseHelper::jsonResponse([
                'status'  => 'success',
                'message' => 'Todas las notificaciones marcadas como leídas',
            ]);
        }

        // Marcar UNA notificación concreta
        if (empty($body['notificacion_id'])) {
            ResponseHelper::error("Falta el parámetro 'notificacion_id'", 400);
        }

        $notif = Notificacion::findById((int)$body['notificacion_id']);

        if (!$notif) {
            ResponseHelper::error('Notificación no encontrada', 404);
        }

        // Verificar que la notificación pertenece al usuario autenticado
        if ($notif->getUsuarioId() !== $usuarioId) {
            ResponseHelper::error('Sin permiso para esta notificación', 403);
        }

        $notif->marcarLeida();

        ResponseHelper::jsonResponse([
            'status'  => 'success',
            'message' => 'Notificación marcada como leída',
        ]);
    }

    else {
        ResponseHelper::error('Método no permitido', 405);
    }

} catch (\PDOException $e) {
    ResponseHelper::error('Error de base de datos: ' . $e->getMessage(), 500);
} catch (\Exception $e) {
    ResponseHelper::error('Error del servidor: ' . $e->getMessage(), 500);
}
