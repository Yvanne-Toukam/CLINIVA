<?php

declare(strict_types=1);

return [
    'session_name' => env('SESSION_NAME', 'cliniva_session'),
    'session_lifetime_minutes' => (int) env('SESSION_LIFETIME_MINUTES', 20),
    'admin_2fa_enabled' => filter_var(env('ADMIN_2FA_ENABLED', true), FILTER_VALIDATE_BOOL),
    'password_algo' => PASSWORD_ARGON2ID,
    'roles' => ['admin', 'medecin', 'secretaire', 'patient'],
];
