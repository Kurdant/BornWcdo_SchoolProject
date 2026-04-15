<?php

declare(strict_types=1);

namespace WCDO\Http;

class Response
{
    public static function success(mixed $data, int $statusCode = 200): never
    {
        self::json(['success' => true, 'data' => $data], $statusCode);
    }

    public static function json(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        // Autorise les requêtes cross-origin depuis le frontend
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $statusCode = 400): never
    {
        self::json(['error' => $message], $statusCode);
    }

    public static function notFound(string $message = 'Ressource non trouvée'): never
    {
        self::json(['error' => $message], 404);
    }

    public static function unauthorized(string $message = 'Non autorisé'): never
    {
        self::json(['error' => $message], 401);
    }
}
