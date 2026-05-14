<?php
// backend/src/Models/Incidencia.php
class Incidencia {
    public static function crear($usuario_id, $tipo, $asunto, $mensaje, $archivo_ruta = null) {
        $db = Database::getInstance()->getConnection();
        $ticket_id = "SR-" . rand(10000, 99999);

        // Añadimos la columna 'archivo' a la consulta
        $stmt = $db->prepare("
            INSERT INTO incidencias (usuario_id, ticket_id, tipo_incidencia, asunto, mensaje, archivo) 
            VALUES (:uid, :tid, :tipo, :asunto, :mensaje, :archivo)
        ");

        $resultado = $stmt->execute([
            'uid'     => $usuario_id,
            'tid'     => $ticket_id,
            'tipo'    => $tipo,
            'asunto'  => $asunto,
            'mensaje' => $mensaje,
            'archivo' => $archivo_ruta
        ]);

        return $resultado ? $ticket_id : false;
    }
}