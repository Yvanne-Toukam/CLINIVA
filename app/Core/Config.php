<?php

declare(strict_types=1);

namespace Cliniva\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static $cache = [];

    public static function get($key, $default = null)
    {
        $parts = array_pad(explode('.', $key, 2), 2, null);
        $file = $parts[0];
        $item = $parts[1];

        if (!isset(self::$cache[$file])) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            self::$cache[$file] = is_file($path) ? require $path : [];
        }

        if ($item === null) {
            return self::$cache[$file] ?: $default;
        }

        return self::$cache[$file][$item] ?? $default;
    }
}
