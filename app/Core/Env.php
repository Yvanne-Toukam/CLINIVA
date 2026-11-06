<?php

declare(strict_types=1);

namespace Cliniva\Core;

final class Env
{
    /** @var array<string, string> */
    private static $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || (strpos($line, '#') === 0) || strpos($line, '=') === false) {
                continue;
            }

            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");

            self::$values[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get($key, $default = null)
    {
        return self::$values[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
