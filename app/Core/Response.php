<?php

declare(strict_types=1);

namespace Cliniva\Core;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . url($path), true, 302);
        exit;
    }
}
