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
    private string $fecha_registro;
    private ?string $ultimo_acceso = null;
    private bool $activo;
    private string $rol;

    // Getters y setters
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

    /**
     * Busca un usuario por email
     */
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

    /**
     * Busca un usuario por ID
     */
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

    /**
     * Crea un nuevo usuario
     */
    public static function create(string $email, string $password, string $dni, string $nombre, string $apellidos): ?self
    {
        $db = Database::getInstance()->getConnection();

        // Comprobar si ya existe
        if (self::findByEmail($email)) {
            throw new Exception("El email ya está registrado");
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO usuarios (email, password_hash, dni, nombre, apellidos, activo, rol)
            VALUES (:email, :password_hash, :dni, :nombre, :apellidos, 1, 'usuario')
        ");

        $result = $stmt->execute([
            'email' => $email,
            'password_hash' => $password_hash,
            'dni' => $dni,
            'nombre' => $nombre,
            'apellidos' => $apellidos
        ]);

        if (!$result) {
            return null;
        }

        $id = $db->lastInsertId();
        return self::findById($id);
    }

    /**
     * Actualiza los datos del usuario
     */
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

    /**
     * Cambia la contraseña
     */
    public function updatePassword(string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuarios SET password_hash = :hash WHERE id = :id");
        return $stmt->execute(['hash' => $hash, 'id' => $this->id]);
    }

    /**
     * Actualiza el último acceso
     */
    public function updateLastAccess(): bool
    {
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Verifica la contraseña
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    /**
     * Hidrata el objeto con datos de la BD
     */
    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->password_hash = $data['password_hash'];
        $this->dni = $data['dni'];
        $this->nombre = $data['nombre'];
        $this->apellidos = $data['apellidos'];
        $this->fecha_registro = $data['fecha_registro'];
        $this->ultimo_acceso = $data['ultimo_acceso'];
        $this->activo = (bool)$data['activo'];
        $this->rol = $data['rol'];
    }
}
