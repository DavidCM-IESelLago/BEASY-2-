<?php

namespace Fintech\Backend;

use PDO;
use Exception;

class Tarjeta extends Model
{
    private int $id;
    private int $cuenta_id;
    private string $numero;
    private string $cvv;
    private string $fecha_expiracion;
    private string $estado;
    private string $fecha_creacion;

    // Getters
    public function getId(): int { return $this->id; }
    public function getCuentaId(): int { return $this->cuenta_id; }
    public function getNumero(): string { return $this->numero; }
    public function getCvv(): string { return $this->cvv; }
    public function getFechaExpiracion(): string { return $this->fecha_expiracion; }
    public function getEstado(): string { return $this->estado; }
    public function getFechaCreacion(): string { return $this->fecha_creacion; }

    /**
     * Obtiene todas las tarjetas de una cuenta
     */
    public static function findByCuentaId(int $cuentaId): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM tarjetas WHERE cuenta_id = :cuenta_id ORDER BY id DESC");
        $stmt->execute(['cuenta_id' => $cuentaId]);
        $rows = $stmt->fetchAll();

        $tarjetas = [];
        foreach ($rows as $row) {
            $t = new self();
            $t->hydrate($row);
            $tarjetas[] = $t;
        }
        return $tarjetas;
    }

    /**
     * Obtiene todas las tarjetas de un usuario (a través de sus cuentas)
     */
    public static function findByUsuarioId(int $usuarioId): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.* FROM tarjetas t
            JOIN cuentas c ON t.cuenta_id = c.id
            WHERE c.usuario_id = :usuario_id
            ORDER BY t.id DESC
        ");
        $stmt->execute(['usuario_id' => $usuarioId]);
        $rows = $stmt->fetchAll();

        $tarjetas = [];
        foreach ($rows as $row) {
            $t = new self();
            $t->hydrate($row);
            $tarjetas[] = $t;
        }
        return $tarjetas;
    }

    /**
     * Crea una nueva tarjeta virtual
     */
    public static function create(int $cuentaId, string $numero, string $cvv, string $fechaExpiracion): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO tarjetas (cuenta_id, numero, cvv, fecha_expiracion, estado)
            VALUES (:cuenta_id, :numero, :cvv, :fecha_expiracion, 'activa')
        ");
        $result = $stmt->execute([
            'cuenta_id' => $cuentaId,
            'numero' => $numero,
            'cvv' => $cvv,
            'fecha_expiracion' => $fechaExpiracion
        ]);

        if (!$result) return null;

        $id = $db->lastInsertId();
        return self::findById($id);
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM tarjetas WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) return null;

        $t = new self();
        $t->hydrate($data);
        return $t;
    }

    /**
     * Actualiza el estado de la tarjeta (activa, bloqueada, cancelada)
     */
    public function updateEstado(string $nuevoEstado): bool
    {
        $this->estado = $nuevoEstado;
        $stmt = $this->db->prepare("UPDATE tarjetas SET estado = :estado WHERE id = :id");
        return $stmt->execute(['estado' => $nuevoEstado, 'id' => $this->id]);
    }

    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->cuenta_id = $data['cuenta_id'];
        $this->numero = $data['numero'];
        $this->cvv = $data['cvv'];
        $this->fecha_expiracion = $data['fecha_expiracion'];
        $this->estado = $data['estado'];
        $this->fecha_creacion = $data['fecha_creacion'];
    }
}