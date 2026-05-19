<?php
namespace Fintech\Backend;

use PDO;

class Incidencia extends Model {

    private int $id;
    private int $usuario_id;
    private string $tipo;
    private string $descripcion;
    private string $estado;
    private string $fecha_creacion;

    
    public function getId(): int { return $this->id; }
    public function getUsuarioId(): int { return $this->usuario_id; }
    public function getTipo(): string { return $this->tipo; }
    public function getDescripcion(): string { return $this->descripcion; }
    public function getEstado(): string { return $this->estado; }
    public function getFechaCreacion(): string { return $this->fecha_creacion; }

    
    public static function crear(int $usuario_id, string $tipo, string $descripcion): ?self {
        
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            INSERT INTO incidencias (usuario_id, tipo, descripcion) 
            VALUES (:usuario_id, :tipo, :descripcion)
        ");

        $resultado = $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':tipo' => $tipo,
            ':descripcion' => $descripcion
        ]);

        if (!$resultado) return null;

        return self::findById($db->lastInsertId());
    }

    
    public static function findByUsuarioId(int $usuarioId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM incidencias
            WHERE usuario_id = :usuario_id
            ORDER BY fecha_creacion DESC
        ");
        $stmt->execute(['usuario_id' => $usuarioId]);

        $incidencias = [];
        foreach ($stmt->fetchAll() as $row) {
            $i = new self();
            $i->hydrate($row);
            $incidencias[] = $i;
        }
        return $incidencias;
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM incidencias WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) return null;

        $i = new self();
        $i->hydrate($data);
        return $i;
    }

    private function hydrate(array $data): void
    {
        $this->id               = $data['id'];
        $this->usuario_id       = $data['usuario_id'];
        $this->tipo             = $data['tipo'];
        $this->descripcion      = $data['descripcion'];
        $this->fecha_creacion = $data['fecha_creacion'];
        $this->estado           = $data['estado'];
    }
}