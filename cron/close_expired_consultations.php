<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Cliniva\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'security.php';

use Cliniva\Core\Env;
use Cliniva\Services\DoctorService;

Env::load(BASE_PATH . DIRECTORY_SEPARATOR . '.env');
date_default_timezone_set((string) env('APP_TIMEZONE', 'Africa/Douala'));

$count = (new DoctorService())->closeExpiredConsultations();
printf("Consultations clôturées automatiquement : %d\n", $count);
