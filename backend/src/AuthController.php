<?php

namespace Fintech\Backend;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

//require_once __DIR__ . '/Usuario.php'; // No es necesario porque el autoload de Composer ya lo carga
class AuthController
{
    private string $key;
    private string $alg = 'HS256';

    public function __construct()
    {
        $this->key = JWT_SECRET;
    }

    public function login(string $email, string $password): string
    {
        $usuario = Usuario::findByEmail($email);

        if (!$usuario || !$usuario->verifyPassword($password)) {
            throw new Exception("Credenciales incorrectas");
        }

        $exp = time() + (defined('JWT_EXPIRATION') ? JWT_EXPIRATION : 3600); // 1 hora por defecto
        $payload = [
            'iss' => "fintech_api",
            'iat' => time(),
            'exp' => $exp,
            'sub' => $usuario->getId()
        ];

        return JWT::encode($payload, $this->key, $this->alg);
    }

    public function verifyToken(string $token): ?int
    {
        try {
            $decoded = JWT::decode($token, new Key($this->key, $this->alg));
            return (int)$decoded->sub;
        } catch (Exception $e) {
            return null;
        }
    }
}
