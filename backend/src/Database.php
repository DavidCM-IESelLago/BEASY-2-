<?php

namespace Fintech\Backend;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $host = DB_HOST;
        $dbname = DB_NAME;
        $user = DB_USER;
        $pass = DB_PASS;
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true, // <--Cambiar a false por seguridad. Con true habilitamos emulación de prepares para evitar problemas con ciertos tipos de datos
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // En desarrollo, podemos mostrar el error; en producción, loguear
            http_response_code(500);
            echo json_encode([
                'error' => 'Error de conexión a la base de datos',
                'debug_message' => $e->getMessage(), // <-- ESTA LÍNEA ES LA CLAVE
                'host_intentado' => $host,
                'usuario_intentado' => $user
            ]);

            exit;
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
