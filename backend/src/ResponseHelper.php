<?php

namespace Fintech\Backend;

class ResponseHelper
{
    public static function jsonResponse($data, int $code = 200): void
    {
        if (ob_get_length()) ob_clean();
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $code = 500)
    {
        self::jsonResponse([
            'status' => 'error',
            'message' => $message
        ], $code);
    }

    
    public static function getBearerToken(): ?string
    {
        $headers = apache_request_headers();
        $authHeader = null;

        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['X-Authorization'])) {
            $authHeader = $headers['X-Authorization'];
        }

        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}