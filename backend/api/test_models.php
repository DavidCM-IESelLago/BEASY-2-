<?php

require_once __DIR__ . '/../config/config.php';

use Fintech\Backend\Usuario;
use Fintech\Backend\Cuenta;
use Fintech\Backend\Transaccion;

// Probamos buscar usuario
$usuario = Usuario::findByEmail('ana@fintech.com');
if ($usuario) {
    echo "Usuario encontrado: " . $usuario->getNombre() . "\n";
    echo "Verificar contraseña 123456: " . ($usuario->verifyPassword('123456') ? 'OK' : 'Fallo') . "\n";
} else {
    echo "Usuario no encontrado\n";
}

// Probamos cuentas
$cuentas = Cuenta::findByUsuarioId($usuario->getId());
echo "Cuentas de Ana:\n";
foreach ($cuentas as $c) {
    echo " - {$c->getNumeroCuenta()}: {$c->getSaldo()}€\n";
}
echo "Saldo total: " . Cuenta::getSaldoTotalByUsuario($usuario->getId()) . "€\n";

// Probamos transferencia
try {
    $cuentaOrigen = $cuentas[0];
    $cuentaDestino = Cuenta::findByNumero('ES001234567890123452'); // Cuenta de Carlos
    Transaccion::transferir($cuentaOrigen->getId(), $cuentaDestino->getId(), 30.0, 'Prueba desde script');
    echo "Transferencia realizada con éxito\n";

    // Verificar nuevo saldo
    $cuentaOrigenActualizada = Cuenta::findById($cuentaOrigen->getId());
    echo "Nuevo saldo origen: {$cuentaOrigenActualizada->getSaldo()}€\n";
} catch (Exception $e) {
    echo "Error en transferencia: " . $e->getMessage() . "\n";
}

// Últimas transacciones
$transacciones = Transaccion::getUltimasByCuenta($cuentaOrigen->getId(), 5);
echo "Últimas transacciones:\n";
foreach ($transacciones as $t) {
    echo " - {$t->getFecha()}: {$t->getMonto()}€\n";
}