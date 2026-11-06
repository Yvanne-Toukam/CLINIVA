<?php

declare(strict_types=1);

return [
    'session_name' => env('SESSION_NAME', 'cliniva_session'),
    'session_lifetime_minutes' => (int) env('SESSION_LIFETIME_MINUTES', 20),
    'password_algo' => PASSWORD_ARGON2ID,
    'roles' => ['admin', 'medecin', 'secretaire', 'patient'],
];
