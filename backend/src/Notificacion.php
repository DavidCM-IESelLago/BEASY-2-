<?php

namespace Fintech\Backend;

use PDO;

class Notificacion extends Model
{
    private int $id;
    private int $usuario_id;
    private string $mensaje;
    private bool $leida;
    private string $fecha;

    // Getters
    public function getId(): int { return $this->id; }
    public function getUsuarioId(): int { return $this->usuario_id; }
    public function getMensaje(): string { return $this->mensaje; }
    public function isLeida(): bool { return $this->leida; }
    public function getFecha(): string { return $this->fecha; }

    /**
     * Obtiene notificaciones de un usuario
     * @param bool $soloNoLeidas Si true, solo devuelve las no leídas
     */
    public static function findByUsuarioId(int $usuarioId, bool $soloNoLeidas = false): array
    {
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT * FROM notificaciones WHERE usuario_id = :usuario_id";
        if ($soloNoLeidas) {
            $sql .= " AND leida = 0";
        }
        $sql .= " ORDER BY fecha DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        $rows = $stmt->fetchAll();

        $notificaciones = [];
        foreach ($rows as $row) {
            $n = new self();
            $n->hydrate($row);
            $notificaciones[] = $n;
        }
        return $notificaciones;
    }

    /**
     * Crea una nueva notificación
     */
    public static function create(int $usuarioId, string $mensaje): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO notificaciones (usuario_id, mensaje, leida, fecha)
            VALUES (:usuario_id, :mensaje, 0, NOW())
        ");
        $result = $stmt->execute([
            'usuario_id' => $usuarioId,
            'mensaje' => $mensaje
        ]);

        if (!$result) return null;

        $id = $db->lastInsertId();
        return self::findById($id);
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM notificaciones WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) return null;

        $n = new self();
        $n->hydrate($data);
        return $n;
    }

    /**
     * Marca la notificación como leída
     */
    public function marcarLeida(): bool
    {
        if ($this->leida) return true;
        $stmt = $this->db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = :id");
        $result = $stmt->execute(['id' => $this->id]);
        if ($result) {
            $this->leida = true;
        }
        return $result;
    }

    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->usuario_id = $data['usuario_id'];
        $this->mensaje = $data['mensaje'];
        $this->leida = (bool)$data['leida'];
        $this->fecha = $data['fecha'];
    }
}