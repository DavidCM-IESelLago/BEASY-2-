<?php

namespace Fintech\Backend;

use PDO;
use Exception;

class Transaccion extends Model
{
    private int $id;
    private int $cuenta_origen_id;
    private int $cuenta_destino_id;
    private float $monto;
    private string $tipo;
    private string $descripcion;
    private string $fecha;
    private string $estado;

    // Getters
    public function getId(): int { return $this->id; }
    public function getCuentaOrigenId(): int { return $this->cuenta_origen_id; }
    public function getCuentaDestinoId(): int { return $this->cuenta_destino_id; }
    public function getMonto(): float { return $this->monto; }
    public function getTipo(): string { return $this->tipo; }
    public function getDescripcion(): string { return $this->descripcion; }
    public function getFecha(): string { return $this->fecha; }
    public function getEstado(): string { return $this->estado; }

    /**
     * Obtiene las últimas transacciones de una cuenta (ordenadas por fecha)
     */
    public static function getUltimasByCuenta(int $cuentaId, int $limite = 10): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM transacciones 
            WHERE cuenta_origen_id = :origen OR cuenta_destino_id = :destino
            ORDER BY fecha DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':origen', $cuentaId, PDO::PARAM_INT);
        $stmt->bindValue(':destino', $cuentaId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $transacciones = [];
        foreach ($rows as $row) {
            $t = new self();
            $t->hydrate($row);
            $transacciones[] = $t;
        }
        return $transacciones;
    }

    /**
     * Obtiene transacciones de un usuario (a través de sus cuentas)
     */
    public static function getByUsuarioId(int $usuarioId, int $page = 1, int $perPage = 10): array
    {
        $db = Database::getInstance()->getConnection();
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare("
            SELECT t.* FROM transacciones t
            JOIN cuentas c ON (t.cuenta_origen_id = c.id OR t.cuenta_destino_id = c.id)
            WHERE c.usuario_id = :usuario_id
            ORDER BY t.fecha DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $transacciones = [];
        foreach ($rows as $row) {
            $t = new self();
            $t->hydrate($row);
            $transacciones[] = $t;
        }
        return $transacciones;
    }

    /**
     * Realiza una transferencia entre cuentas con control de concurrencia
     */
    public static function transferir(int $cuentaOrigenId, int $cuentaDestinoId, float $monto, string $descripcion = ''): bool
    {
        $db = Database::getInstance()->getConnection();

        // Iniciamos la transacción
        $db->beginTransaction();

        try {
            // Bloquear las filas para evitar condiciones de carrera
            $stmtOrigen = $db->prepare("SELECT * FROM cuentas WHERE id = :id FOR UPDATE");
            $stmtOrigen->execute(['id' => $cuentaOrigenId]);
            $cuentaOrigen = $stmtOrigen->fetch();

            $stmtDestino = $db->prepare("SELECT * FROM cuentas WHERE id = :id FOR UPDATE");
            $stmtDestino->execute(['id' => $cuentaDestinoId]);
            $cuentaDestino = $stmtDestino->fetch();

            if (!$cuentaOrigen || !$cuentaDestino) {
                throw new Exception("Una de las cuentas no existe");
            }

            if ($cuentaOrigen['activa'] != 1 || $cuentaDestino['activa'] != 1) {
                throw new Exception("Alguna de las cuentas está inactiva");
            }

            if ($cuentaOrigen['saldo'] < $monto) {
                throw new Exception("Saldo insuficiente");
            }

            // Calcular nuevos saldos
            $nuevoSaldoOrigen = $cuentaOrigen['saldo'] - $monto;
            $nuevoSaldoDestino = $cuentaDestino['saldo'] + $monto;

            // Actualizar saldos
            $updateOrigen = $db->prepare("UPDATE cuentas SET saldo = :saldo WHERE id = :id");
            $updateOrigen->execute(['saldo' => $nuevoSaldoOrigen, 'id' => $cuentaOrigenId]);

            $updateDestino = $db->prepare("UPDATE cuentas SET saldo = :saldo WHERE id = :id");
            $updateDestino->execute(['saldo' => $nuevoSaldoDestino, 'id' => $cuentaDestinoId]);

            // Registrar la transacción
            $insert = $db->prepare("
                INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, estado, fecha)
                VALUES (:origen, :destino, :monto, 'transferencia', :descripcion, 'completada', NOW())
            ");
            $insert->execute([
                'origen' => $cuentaOrigenId,
                'destino' => $cuentaDestinoId,
                'monto' => $monto,
                'descripcion' => $descripcion
            ]);

            // Insertar notificaciones para ambos usuarios
            // Primero obtenemos los usuarios asociados a las cuentas
            $usuarioOrigen = $cuentaOrigen['usuario_id'];
            $usuarioDestino = $cuentaDestino['usuario_id'];

            $notifOrigen = $db->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)");
            $notifOrigen->execute([
                'uid' => $usuarioOrigen,
                'msg' => "Has transferido $monto € a la cuenta " . $cuentaDestino['numero_cuenta']
            ]);

            $notifDestino = $db->prepare("INSERT INTO notificaciones (usuario_id, mensaje) VALUES (:uid, :msg)");
            $notifDestino->execute([
                'uid' => $usuarioDestino,
                'msg' => "Has recibido $monto € desde la cuenta " . $cuentaOrigen['numero_cuenta']
            ]);

            $db->commit();
            return true;

        } catch (Exception $e) {
            $db->rollBack();
            // Podríamos loguear el error, pero lanzamos excepción para que el controlador la gestione
            throw $e;
        }
    }

    /**
     * Crea una transacción (sin actualizar saldos, para registros manuales)
     */
    public static function create(int $origenId, int $destinoId, float $monto, string $tipo, string $descripcion = '', string $estado = 'pendiente'): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO transacciones (cuenta_origen_id, cuenta_destino_id, monto, tipo, descripcion, estado)
            VALUES (:origen, :destino, :monto, :tipo, :descripcion, :estado)
        ");
        $result = $stmt->execute([
            'origen' => $origenId,
            'destino' => $destinoId,
            'monto' => $monto,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'estado' => $estado
        ]);

        if (!$result) return null;

        $id = $db->lastInsertId();
        return self::findById($id);
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM transacciones WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) return null;

        $t = new self();
        $t->hydrate($data);
        return $t;
    }

    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->cuenta_origen_id = $data['cuenta_origen_id'];
        $this->cuenta_destino_id = $data['cuenta_destino_id'];
        $this->monto = (float)$data['monto'];
        $this->tipo = $data['tipo'];
        $this->descripcion = $data['descripcion'];
        $this->fecha = $data['fecha'];
        $this->estado = $data['estado'];
    }
}