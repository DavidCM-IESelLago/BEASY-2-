<?php
// 1. Cargamos configuración y dependencias
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AuthController.php';
require_once __DIR__ . '/../src/ResponseHelper.php';

use Fintech\Backend\AuthController;
use Fintech\Backend\Cuenta;
use Fintech\Backend\ResponseHelper;
use Fintech\Backend\Transaccion;

try {
    // Validar token y obtener usuario
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    if (!$usuarioId) {
        ResponseHelper::error('Token no válido o no proporcionado', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ResponseHelper::error('Método no permitido', 405);
    }

    $saldoTotal = Cuenta::getSaldoTotalByUsuario($usuarioId);

    $movimientos = Transaccion::getByUsuarioId($usuarioId, 1, 5);

    $ultimosMovimientos = [];
    foreach ($movimientos as $mov) {
        
        static $cuentasUsuario = null;
        if ($cuentasUsuario === null) {
            $cuentas = Cuenta::findByUsuarioId($usuarioId);
            $cuentasUsuario = array_map(function($c) {
                return $c->getId();
            }, $cuentas);
        }

        $esGasto = in_array($mov->getCuentaOrigenId(), $cuentasUsuario);
        $montoConSigno = $esGasto ? -$mov->getMonto() : $mov->getMonto();

        $ultimosMovimientos[] = [
            "fecha" => $mov->getFecha(),
            "concepto" => $mov->getDescripcion() ?: ($esGasto ? "Transferencia enviada" : "Transferencia recibida"),
            "monto" => $montoConSigno
        ];
    }

    // Estadísticas de gastos/movimientos: agrupados en dos categorías "Gastos" e "Ingresos", con los totales del último mes.
    $ultimoMes = date('d-m-Y H:i:s', strtotime('-30 days'));
    
    //Obtener todas las transacciones del usuario sin paginación para el último mes (se puede optimizar con una consulta específica)
    $todasTransacciones = Transaccion::getByUsuarioId($usuarioId, 1, 100); //se asume max 100

    $totalGastos = 0;
    $totalIngresos = 0;
    foreach ($todasTransacciones as $t) {
        if ($t->getFecha() < $ultimoMes) continue;
        //determinar si es gasto o ingreso
        $esGasto = in_array($t->getCuentaOrigenId(), $cuentasUsuario);
        if ($esGasto) {
            $totalGastos += $t->getMonto();
        } else {
            $totalIngresos += $t->getMonto();
        }
    }

    $estadisticasGastos = [
        "Gastos" => $totalGastos,
        "Ingresos" => $totalIngresos
    ];

    ResponseHelper::jsonResponse([
        "status" => "success",
        "usuario_id" => $usuarioId,
        "dashboard" => [
            "saldo_total" => $saldoTotal,
            "ultimos_movimientos" => $ultimosMovimientos,
            "estadisticas_gastos" => $estadisticasGastos
        ]
    ]);
     
} catch (\PDOException $e) {
    // Captura errores de DB
    ResponseHelper::error("Error de base de datos: " . $e->getMessage(), 500);
} catch (\Exception $e) {
    // Captura cualquier otro error
    ResponseHelper::error("Error interno del servidor", 500);
}
