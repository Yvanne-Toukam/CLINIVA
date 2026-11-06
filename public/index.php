<?php

declare(strict_types=1);

use Cliniva\Controllers\HomeController;
use Cliniva\Core\Env;
use Cliniva\Core\Router;
use Cliniva\Middleware\SecurityHeadersMiddleware;

define('BASE_PATH', dirname(__DIR__));

$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Cliniva\\';
        if (substr($class, 0, strlen($prefix)) !== $prefix) {
            return;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = BASE_PATH . '/app/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });

    require BASE_PATH . '/app/Helpers/security.php';
}

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Douala'));

secure_session_start();
(new SecurityHeadersMiddleware())->handle();
require_csrf();

$router = new Router();
require BASE_PATH . '/config/routes.php';

$router->get('/', [HomeController::class, 'index']);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
