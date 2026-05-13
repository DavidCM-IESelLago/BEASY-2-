<?php

namespace Fintech\Backend;

use PDO;

class Cuenta extends Model
{
    private int $id;
    private int $usuario_id;
    private string $numero_cuenta;
    private float $saldo;
    private string $tipo;
    private string $fecha_creacion;
    private bool $activa;

    // Getters y setters
    public function getId(): int { return $this->id; }
    public function getUsuarioId(): int { return $this->usuario_id; }
    public function getNumeroCuenta(): string { return $this->numero_cuenta; }
    public function getSaldo(): float { return $this->saldo; }
    public function getTipo(): string { return $this->tipo; }
    public function getFechaCreacion(): string { return $this->fecha_creacion; }
    public function isActiva(): bool { return $this->activa; }
    public function setSaldo(float $saldo): void { $this->saldo = $saldo; }
    public function setFechaCreacion(string $fechaCreacion): void { $this->fecha_creacion = $fechaCreacion; }

    /**
     * Obtiene todas las cuentas de un usuario
     */
    public static function findByUsuarioId(int $usuarioId): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM cuentas WHERE usuario_id = :usuario_id ORDER BY id");
        $stmt->execute(['usuario_id' => $usuarioId]);
        $rows = $stmt->fetchAll();

        $cuentas = [];
        foreach ($rows as $row) {
            $cuenta = new self();
            $cuenta->hydrate($row);
            $cuentas[] = $cuenta;
        }
        return $cuentas;
    }

    /**
     * Obtiene saldo total de todas las cuentas de un usuario
     */
    public static function getSaldoTotalByUsuario(int $usuarioId): float
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT SUM(saldo) as total FROM cuentas WHERE usuario_id = :usuario_id AND activa = 1");
        $stmt->execute(['usuario_id' => $usuarioId]);
        $result = $stmt->fetch();
        return (float)($result['total'] ?? 0);
    }

    /**
     * Busca una cuenta por su número
     */
    public static function findByNumero(string $numero): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM cuentas WHERE numero_cuenta = :numero LIMIT 1");
        $stmt->execute(['numero' => $numero]);
        $data = $stmt->fetch();

        if (!$data) return null;

        $cuenta = new self();
        $cuenta->hydrate($data);
        return $cuenta;
    }

    /**
     * Crea una nueva cuenta asociada a un usuario (se usa al registrar)
     */
    public static function create(int $usuarioId, string $numeroCuenta, string $tipo = 'corriente', float $saldoInicial = 0.0): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO cuentas (usuario_id, numero_cuenta, saldo, tipo, activa)
            VALUES (:usuario_id, :numero_cuenta, :saldo, :tipo, 1)
        ");
        $result = $stmt->execute([
            'usuario_id' => $usuarioId,
            'numero_cuenta' => $numeroCuenta,
            'saldo' => $saldoInicial,
            'tipo' => $tipo
        ]);

        if (!$result) return null;

        $id = $db->lastInsertId();
        return self::findById($id);
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM cuentas WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) return null;

        $cuenta = new self();
        $cuenta->hydrate($data);
        return $cuenta;
    }

    /**
     * Actualiza el saldo de la cuenta
     */
    public function updateSaldo(float $nuevoSaldo): bool
    {
        $this->saldo = $nuevoSaldo;
        $stmt = $this->db->prepare("UPDATE cuentas SET saldo = :saldo WHERE id = :id");
        return $stmt->execute(['saldo' => $nuevoSaldo, 'id' => $this->id]);
    }

    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->usuario_id = $data['usuario_id'];
        $this->numero_cuenta = $data['numero_cuenta'];
        $this->saldo = (float)$data['saldo'];
        $this->tipo = $data['tipo'];
        $this->fecha_creacion = $data['fecha_creacion'];
        $this->activa = (bool)$data['activa'];
    }
}