<?php

use Fintech\Backend\AuthController;
use Fintech\Backend\ResponseHelper;

function getTokenFromHeaders(): string {
    $headers = getallheaders();

    $token = $headers['X-Beasy-Token'] ?? '';
    if (!empty($token)) return $token;

    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    return str_replace('Bearer ', '', $auth);
}

function validateToken():int {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../src/AuthController.php';

    $token = getTokenFromHeaders();

    if (empty($token)) {
        ResponseHelper::error('Token no recibido', 401);
    }

    $auth = new AuthController();
    $usuarioId = $auth->verifyToken($token);

    if (!$usuarioId) {
        ResponseHelper::error('Token inválido', 401);
    }

    return $usuarioId;
}