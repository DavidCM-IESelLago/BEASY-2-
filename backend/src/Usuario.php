<?php

namespace Fintech\Backend;

use PDO;
use Exception;

require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/Database.php';
class Usuario extends Model
{
    private int $id;
    private string $email;
    private string $password_hash;
    private string $dni;
    private string $nombre;
    private string $apellidos;
    private ?string $telefono = null;
    private string $fecha_registro;
    private ?string $ultimo_acceso = null;
    private bool $activo;
    private string $rol;

    
    public function getId(): int
    {
        return $this->id;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getDni(): string
    {
        return $this->dni;
    }
    public function getNombre(): string
    {
        return $this->nombre;
    }
    public function getApellidos(): string
    {
        return $this->apellidos;
    }
    public function getTelefono(): ?string
    {
        return $this->telefono;
    }
    public function getFechaRegistro(): string
    {
        return $this->fecha_registro;
    }
    public function getUltimoAcceso(): ?string
    {
        return $this->ultimo_acceso;
    }
    public function isActivo(): bool
    {
        return $this->activo;
    }
    public function getRol(): string
    {
        return $this->rol;
    }
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
    public function setDni(string $dni): void
    {
        $this->dni = $dni;
    }
    public function setNombre(string $nombre): void
    {
        $this->nombre = $nombre;
    }
    public function setApellidos(string $apellidos): void
    {
        $this->apellidos = $apellidos;
    }
    public function setActivo(bool $activo): void
    {
        $this->activo = $activo;
    }
    public function setRol(string $rol): void
    {
        $this->rol = $rol;
    }

    
    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        $usuario = new self();
        $usuario->hydrate($data);
        return $usuario;
    }

    
    public static function findByDni(string $dni): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE dni = :dni LIMIT 1");
        $stmt->execute(['dni' => $dni]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        $usuario = new self();
        $usuario->hydrate($data);
        return $usuario;
    }

    
    public static function findById(int $id): ?self
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();

        if (!$data) {
            return null;
        }

        $usuario = new self();
        $usuario->hydrate($data);
        return $usuario;
    }

    
public static function create(string $dni, string $email, string $password, string $nombre, string $apellidos, ?string $telefono = null): ?self
{
    $db = Database::getInstance()->getConnection();

    
    if (self::findByEmail($email)) {
        throw new Exception("El email ya está registrado");
    }

    if (self::findByDni($dni)) {
        throw new Exception("El dni ya está registrado");
    }

    
    if ($telefono !== null && $telefono !== '') {
        $stmtTel = $db->prepare("SELECT id FROM usuarios WHERE telefono = :tel LIMIT 1");
        $stmtTel->execute(['tel' => $telefono]);
        if ($stmtTel->fetch()) {
            throw new Exception("El teléfono ya está registrado");
        }
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    
    $stmt = $db->prepare("
        INSERT INTO usuarios (dni, email, password_hash, nombre, apellidos, telefono, activo, rol)
        VALUES (:dni, :email, :password_hash, :nombre, :apellidos, :telefono, 1, 'usuario')
    ");

    
    $result = $stmt->execute([
        'dni'           => $dni,
        'email'         => $email,
        'password_hash' => $password_hash,
        'nombre'        => $nombre,
        'apellidos'     => $apellidos,
        'telefono'      => $telefono
    ]);

    if (!$result) {
        return null;
    }

    $id = $db->lastInsertId();
    return self::findById($id);
}

    
    public function update(): bool
    {
        $stmt = $this->db->prepare("
            UPDATE usuarios 
            SET email = :email, nombre = :nombre, apellidos = :apellidos 
            WHERE id = :id
        ");
        return $stmt->execute([
            'email' => $this->email,
            'nombre' => $this->nombre,
            'apellidos' => $this->apellidos,
            'id' => $this->id
        ]);
    }

    
    public function updatePassword(string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
        return $stmt->execute(['hash' => $hash, 'id' => $this->id]);
    }

    
    public function updateLastAccess(): bool
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    
    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->password_hash = $data['password_hash'];
        $this->dni = $data['dni'];
        $this->nombre = $data['nombre'];
        $this->apellidos = $data['apellidos'];
        $this->telefono = $data['telefono'] ?? null;
        $this->fecha_registro = $data['fecha_registro'];
        $this->ultimo_acceso = $data['ultimo_acceso'];
        $this->activo = (bool)$data['activo'];
        $this->rol = $data['rol'];
    }
}
