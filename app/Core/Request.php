<?php

declare(strict_types=1);

namespace Cliniva\Core;

final class Request
{
    public static function input($key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function isJson(): bool
    {
        return strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }

    /** @return array<string, mixed> */
    public static function json(): array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
        return is_array($payload) ? $payload : [];
    }
}
