<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Config;
use Cliniva\Core\Database;
use Cliniva\Models\User;
use PDO;

final class AuthService
{
    private $users;
    private $notifications;
    private $passwordPolicy;

    public function __construct()
    {
        $this->users = new User();
        $this->notifications = new NotificationService();
        $this->passwordPolicy = new PasswordPolicy();
    }

    /** @return array{user_id:int, verification_link:string} */
    public function registerPatient(array $input): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $errors = $this->validateRegistration($input);

        if ($this->users->findByEmail($email) !== null) {
            $errors[] = 'Cette adresse e-mail est deja utilisee.';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        return Database::transaction(function (PDO $pdo) use ($input, $email, $password): array {
            $stmt = $pdo->prepare('INSERT INTO users (role, first_name, last_name, email, phone, password_hash, consent_at, is_active, created_at)
                VALUES ("patient", :first_name, :last_name, :email, :phone, :password_hash, NOW(), 1, NOW())');
            $stmt->execute([
                'first_name' => trim((string) $input['first_name']),
                'last_name' => trim((string) $input['last_name']),
                'email' => $email,
                'phone' => trim((string) $input['phone']),
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            ]);
            $userId = (int) $pdo->lastInsertId();
            $recordNumber = sprintf('CLV-%s-%06d', date('Y'), $userId);

            $stmt = $pdo->prepare('INSERT INTO patients (user_id, record_number, first_name, last_name, birth_date, sex, phone, email, consent_at, created_at)
                VALUES (:user_id, :record_number, :first_name, :last_name, :birth_date, :sex, :phone, :email, NOW(), NOW())');
            $stmt->execute([
                'user_id' => $userId,
                'record_number' => $recordNumber,
                'first_name' => trim((string) $input['first_name']),
                'last_name' => trim((string) $input['last_name']),
                'birth_date' => (string) $input['birth_date'],
                'sex' => (string) $input['sex'],
                'phone' => trim((string) $input['phone']),
                'email' => $email,
            ]);
            $patientId = (int) $pdo->lastInsertId();

            $token = random_token();
            $stmt = $pdo->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
                VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())');
            $stmt->execute(['user_id' => $userId, 'token_hash' => hash_token($token)]);

            $link = url('/verify-email?email=' . rawurlencode($email) . '&token=' . rawurlencode($token));
            $this->notifications->queueEmail($userId, $patientId, $email, 'Verification de votre compte Cliniva', "Bonjour,\n\nConfirmez votre compte Cliniva : {$link}\n\nCe lien expire dans 24 heures.");

            return ['user_id' => $userId, 'verification_link' => $link];
        });
    }

    public function verifyEmail(string $email, string $token): bool
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null) {
            return false;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM email_verifications WHERE user_id = :user_id AND token_hash = :token_hash AND verified_at IS NULL AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['user_id' => $user['id'], 'token_hash' => hash_token($token)]);
        $verification = $stmt->fetch();

        if (!is_array($verification)) {
            return false;
        }

        Database::transaction(function (PDO $pdo) use ($user, $verification): void {
            $stmt = $pdo->prepare('UPDATE email_verifications SET verified_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $verification['id']]);
            $this->users->markEmailVerified((int) $user['id']);
        });

        return true;
    }

    public function authenticate(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        if ($this->isLoginLocked($email, client_ip())) {
            return null;
        }

        $user = $this->users->findByEmail($email);
        $valid = is_array($user) && (int) $user['is_active'] === 1 && password_verify($password, (string) $user['password_hash']);

        $this->recordLoginAttempt($email, $user['id'] ?? null, client_ip(), $valid);

        if (!$valid) {
            return null;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            $this->users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_ARGON2ID));
        }

        return $user;
    }

    public function isLoginLocked(string $email, string $ip): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM login_attempts
            WHERE success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (email = :email OR ip_address = :ip)');
        $stmt->execute(['email' => $email, 'ip' => $ip]);

        return (int) $stmt->fetchColumn() >= 5;
    }

    public function recordLoginAttempt(string $email, $userId, string $ip, bool $success): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO login_attempts (email, user_id, ip_address, success, locked_until, created_at)
            VALUES (:email, :user_id, :ip, :success, :locked_until, NOW())');
        $stmt->execute([
            'email' => $email,
            'user_id' => $userId === null ? null : (int) $userId,
            'ip' => $ip,
            'success' => $success ? 1 : 0,
            'locked_until' => $success ? null : date('Y-m-d H:i:s', time() + 900),
        ]);
    }

    public function createPasswordReset(string $email)
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null || (int) $user['is_active'] !== 1) {
            return null;
        }

        $token = random_token();
        $stmt = Database::pdo()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
            VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())');
        $stmt->execute(['user_id' => $user['id'], 'token_hash' => hash_token($token)]);

        $link = url('/password/reset?email=' . rawurlencode((string) $user['email']) . '&token=' . rawurlencode($token));
        $this->notifications->queueEmail((int) $user['id'], null, (string) $user['email'], 'Reinitialisation du mot de passe Cliniva', "Lien de reinitialisation : {$link}\n\nCe lien expire dans 1 heure.");

        return $link;
    }

    public function resetPassword(string $email, string $token, string $password, string $confirmation): bool
    {
        $errors = $this->passwordPolicy->errors($password, $confirmation);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null) {
            return false;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM password_resets WHERE user_id = :user_id AND token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['user_id' => $user['id'], 'token_hash' => hash_token($token)]);
        $reset = $stmt->fetch();
        if (!is_array($reset)) {
            return false;
        }

        Database::transaction(function (PDO $pdo) use ($user, $reset, $password): void {
            $updateUser = $pdo->prepare('UPDATE users SET password_hash = :password_hash, must_change_password = 0, updated_at = NOW() WHERE id = :id');
            $updateUser->execute(['password_hash' => password_hash($password, PASSWORD_ARGON2ID), 'id' => (int) $user['id']]);
            $stmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $reset['id']]);
        });

        return true;
    }

    public function changeCurrentPassword(int $userId, string $password, string $confirmation): void
    {
        $errors = $this->passwordPolicy->errors($password, $confirmation);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = :password_hash,
            must_change_password = 0, updated_at = NOW()
            WHERE id = :id AND is_active = 1 AND deleted_at IS NULL');
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'id' => $userId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Le compte ne peut pas être modifié.');
        }
    }

    public function completeLogin(array $user): void
    {
        $this->users->touchLogin((int) $user['id']);
        login_user($user);
    }

    /** @return array<int, string> */
    private function validateRegistration(array $input): array
    {
        $errors = [];
        $required = ['first_name', 'last_name', 'birth_date', 'sex', 'phone', 'email', 'password', 'password_confirmation'];
        foreach ($required as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[] = 'Le champ ' . $field . ' est obligatoire.';
            }
        }

        if (!filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        if (!in_array($input['sex'] ?? '', ['F', 'M', 'Autre'], true)) {
            $errors[] = 'Sexe invalide.';
        }

        if (empty($input['consent'])) {
            $errors[] = 'Le consentement est obligatoire pour creer un compte patient.';
        }

        return array_merge($errors, $this->passwordPolicy->errors((string) ($input['password'] ?? ''), (string) ($input['password_confirmation'] ?? '')));
    }
}
