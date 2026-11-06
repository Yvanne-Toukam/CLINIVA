<?php

declare(strict_types=1);

namespace Cliniva\Middleware;

use Cliniva\Core\Response;
use Cliniva\Core\Database;

final class RbacMiddleware
{
    /** @param array<int, string> $allowedRoles */
    public function handle(array $allowedRoles): void
    {
        $user = current_user();
        if ($user !== null) {
            $stmt = Database::pdo()->prepare('SELECT role, is_active FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => (int) $user['id']]);
            $account = $stmt->fetch();
            if (!is_array($account) || (int) $account['is_active'] !== 1 || $account['role'] !== $user['role']) {
                logout_user();
                secure_session_start();
                flash('warning', 'Les droits de votre compte ont changé. Reconnectez-vous.');
                Response::redirect('/login');
            }
        }

        if ($user !== null && !empty($user['must_change_password'])) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $basePath = dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
            if ($basePath !== '/' && $basePath !== '.' && strpos($path, $basePath . '/') === 0) {
                $path = substr($path, strlen($basePath));
            }
            if (!in_array($path, ['/password/change', '/logout'], true)) {
                Response::redirect('/password/change');
            }
        }

        if ($allowedRoles === []) {
            return;
        }

        if (current_user() === null) {
            Response::redirect('/');
        }

        if (!user_can(...$allowedRoles)) {
            http_response_code(403);
            exit('Acces refuse.');
        }
    }
}
