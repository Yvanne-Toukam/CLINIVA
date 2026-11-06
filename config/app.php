<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Cliniva'),
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', 'http://localhost/CLINIV/public'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Africa/Douala'),
    'currency' => env('APP_CURRENCY', 'XAF'),
];
