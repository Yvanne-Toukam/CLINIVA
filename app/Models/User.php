<?php

declare(strict_types=1);

namespace Cliniva\Models;

final class User extends BaseModel
{
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT u.*,
                (SELECT p.sex FROM patients p WHERE p.user_id = u.id AND p.deleted_at IS NULL ORDER BY p.id ASC LIMIT 1) AS sex
            FROM users u
            WHERE u.email = :email AND u.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT u.*,
                (SELECT p.sex FROM patients p WHERE p.user_id = u.id AND p.deleted_at IS NULL ORDER BY p.id ASC LIMIT 1) AS sex
            FROM users u
            WHERE u.id = :id AND u.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public function updatePassword(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }

    public function markEmailVerified(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateTotpSecret(int $id, string $encryptedSecret): void
    {
        $stmt = $this->db->prepare('UPDATE users SET totp_secret_encrypted = :secret, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['secret' => $encryptedSecret, 'id' => $id]);
    }

    public function touchLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
