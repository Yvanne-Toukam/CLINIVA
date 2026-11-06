<?php

declare(strict_types=1);

use Cliniva\Core\Config;
use Cliniva\Core\Env;

function env($key, $default = null)
{
    return Env::get($key, $default);
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_base_url(): string
{
    $configured = (string) Config::get('app.url', '');
    $host = $_SERVER['HTTP_HOST'] ?? parse_url($configured, PHP_URL_HOST) ?: 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $path = parse_url($configured, PHP_URL_PATH) ?: '';

    if (isset($_SERVER['SCRIPT_NAME'])) {
        $scriptPath = dirname(str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']));
        if ($scriptPath !== '/' && $scriptPath !== '.') {
            $path = $scriptPath;
        }
    }

    $path = rtrim((string) $path, '/');
    return $scheme . '://' . $host . ($path !== '' && $path !== '\\' ? $path : '');
}

function url(string $path = ''): string
{
    $base = request_base_url();
    return $base . '/' . ltrim($path, '/');
}

function relative_url(string $path = ''): string
{
    $base = rtrim((string) parse_url(request_base_url(), PHP_URL_PATH) ?: '', '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function is_production(): bool
{
    return Config::get('app.env') === 'production';
}

function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = is_production();
    session_name((string) Config::get('security.session_name', 'cliniva_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    $lifetime = (int) Config::get('security.session_lifetime_minutes', 20) * 60;
    if (isset($_SESSION['_last_activity']) && time() - (int) $_SESSION['_last_activity'] > $lifetime) {
        secure_session_destroy();
        session_start();
    }

    $_SESSION['_last_activity'] = time();
}

function secure_session_destroy(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token): bool
{
    return is_string($token) && isset($_SESSION['_csrf_token']) && hash_equals((string) $_SESSION['_csrf_token'], $token);
}

function require_csrf(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verify(is_string($token) ? $token : null)) {
        http_response_code(419);
        exit('Jeton CSRF invalide.');
    }
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'role' => (string) $user['role'],
        'first_name' => (string) $user['first_name'],
        'last_name' => (string) $user['last_name'],
        'email' => (string) ($user['email'] ?? ''),
        'must_change_password' => (int) ($user['must_change_password'] ?? 0),
    ];
    $_SESSION['_last_activity'] = time();
}

function logout_user(): void
{
    secure_session_destroy();
}

function current_role()
{
    return current_user()['role'] ?? null;
}

function user_can(string ...$roles): bool
{
    $role = current_role();
    return $role !== null && in_array($role, $roles, true);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type][] = $message;
}

function flashes($type = null): array
{
    $messages = $_SESSION['_flash'] ?? [];

    if ($type !== null) {
        $selected = $messages[$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $selected;
    }

    unset($_SESSION['_flash']);
    return $messages;
}

function old($key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function remember_old(array $input): void
{
    unset($input['password'], $input['password_confirmation'], $input['_csrf_token'], $input['totp_code']);
    $_SESSION['_old'] = $input;
}

function forget_old(): void
{
    unset($_SESSION['_old']);
}

function form_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

function remember_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', 0, 45);
}

function hash_token(string $token): string
{
    return hash('sha256', $token);
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function app_setting(string $key, $default = null)
{
    static $cache = [];
    $settings = [
        'appointment_cancel_min_hours' => ['env' => 'APPOINTMENT_CANCEL_MIN_HOURS', 'default' => 6, 'min' => 0, 'max' => 720],
        'appointment_validation_hours' => ['env' => 'APPOINTMENT_VALIDATION_HOURS', 'default' => 24, 'min' => 1, 'max' => 168],
    ];
    if (!isset($settings[$key])) {
        return $default;
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $definition = $settings[$key];
    $fallback = (int) env($definition['env'], $default ?? $definition['default']);
    try {
        $stmt = \Cliniva\Core\Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = filter_var($stmt->fetchColumn(), FILTER_VALIDATE_INT);
        if ($value !== false && $value >= $definition['min'] && $value <= $definition['max']) {
            return $cache[$key] = $value;
        }
    } catch (\Throwable) {
    }

    return $cache[$key] = $fallback;
}

function sensitive_key(): string
{
    $raw = (string) env('SODIUM_KEY_BASE64', '');
    $decoded = base64_decode($raw, true);

    if (function_exists('sodium_crypto_secretbox') && defined('SODIUM_CRYPTO_SECRETBOX_KEYBYTES')) {
        if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $decoded;
        }

        if (is_production()) {
            throw new RuntimeException('SODIUM_KEY_BASE64 doit contenir une clé sodium valide en production.');
        }

        return substr(hash('sha256', (string) env('APP_KEY', 'cliniva-local-key'), true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    if (is_production()) {
        throw new RuntimeException('L’extension sodium est obligatoire en production pour chiffrer les données médicales.');
    }

    $material = $decoded !== false && is_string($decoded) && $decoded !== '' ? $decoded : (string) env('APP_KEY', 'cliniva-local-key');
    return hash('sha256', $material, true);
}

function encrypt_sensitive(string $plainText): string
{
    if (function_exists('sodium_crypto_secretbox') && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainText, $nonce, sensitive_key());
        return base64_encode($nonce . $cipher);
    }

    $key = sensitive_key();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return base64_encode($plainText);
    }

    return base64_encode($iv . $cipher);
}

function decrypt_sensitive($cipherText)
{
    if ($cipherText === null || $cipherText === '') {
        return null;
    }

    if (function_exists('sodium_crypto_secretbox') && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')) {
        $decoded = base64_decode($cipherText, true);
        if (!is_string($decoded) || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, sensitive_key());

        return is_string($plain) ? $plain : null;
    }

    $decoded = base64_decode((string) $cipherText, true);
    if (!is_string($decoded) || strlen($decoded) <= 16) {
        return null;
    }

    $iv = substr($decoded, 0, 16);
    $cipher = substr($decoded, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', sensitive_key(), OPENSSL_RAW_DATA, $iv);

    return $plain === false ? null : $plain;
}
