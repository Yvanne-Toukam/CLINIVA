<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use PDO;

final class PublicSiteService
{
    /** @return array<string, mixed> */
    public function publicData(): array
    {
        $pdo = Database::pdo();
        $specialties = $pdo->query('SELECT name, description FROM specialties WHERE deleted_at IS NULL ORDER BY name ASC')->fetchAll();
        $doctors = $pdo->query('SELECT u.first_name, u.last_name, s.name AS specialty
            FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN specialties s ON s.id = d.specialty_id
            WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL AND u.is_active = 1 AND s.deleted_at IS NULL
            ORDER BY u.last_name ASC, u.first_name ASC
            LIMIT 6')->fetchAll();
        $doctorCount = (int) $pdo->query('SELECT COUNT(*) FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL AND u.is_active = 1')->fetchColumn();
        $specialtyCount = (int) $pdo->query('SELECT COUNT(*) FROM specialties WHERE deleted_at IS NULL')->fetchColumn();
        $settings = $pdo->query('SELECT setting_key, setting_value FROM settings
            WHERE setting_key IN ("clinic_email", "clinic_phone", "clinic_address", "clinic_opening_hours")')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'specialties' => $specialties,
            'doctors' => $doctors,
            'publicContact' => [
                'email' => (string) ($settings['clinic_email'] ?? env('CONTACT_EMAIL', 'contact@cliniva.cm')),
                'phone' => (string) ($settings['clinic_phone'] ?? env('CONTACT_PHONE', '')),
                'address' => (string) ($settings['clinic_address'] ?? env('CLINIC_ADDRESS', 'Douala, Cameroun')),
                'hours' => (string) ($settings['clinic_opening_hours'] ?? 'Horaires à confirmer'),
            ],
            'counts' => [
                'doctors' => $doctorCount,
                'specialties' => $specialtyCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return bool False when the IP has exceeded the rate limit.
     */
    public function submitContact(array $input): bool
    {
        if (trim((string) ($input['website'] ?? '')) !== '') {
            return true;
        }

        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Le nom doit contenir entre 2 et 120 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Veuillez saisir une adresse e-mail valide.';
        }
        if (mb_strlen($phone) > 40) {
            $errors[] = 'Le numéro de téléphone est trop long.';
        }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
            $errors[] = 'Le message doit contenir entre 10 et 5000 caractères.';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $pdo = Database::pdo();
        $ipAddress = client_ip();
        $limit = $pdo->prepare('SELECT COUNT(*) FROM contact_messages
            WHERE ip_address = :ip_address AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
        $limit->execute(['ip_address' => $ipAddress]);

        if ((int) $limit->fetchColumn() >= 3) {
            return false;
        }

        Database::transaction(function (PDO $pdo) use ($name, $email, $phone, $message, $ipAddress): void {
            $insert = $pdo->prepare('INSERT INTO contact_messages
                (name, email, phone, message, ip_address, status, created_at)
                VALUES (:name, :email, :phone, :message, :ip_address, "nouveau", NOW())');
            $insert->execute([
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'message' => $message,
                'ip_address' => $ipAddress,
            ]);

            $admin = $pdo->query('SELECT id, email FROM users
                WHERE role = "admin" AND is_active = 1 AND deleted_at IS NULL AND email IS NOT NULL
                ORDER BY id ASC LIMIT 1')->fetch();

            if (is_array($admin) && filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
                (new NotificationService())->queueEmail(
                    (int) $admin['id'],
                    null,
                    (string) $admin['email'],
                    'Nouveau message reçu sur Cliniva',
                    "Un nouveau message a été envoyé depuis le formulaire public.\n"
                        . "Nom : {$name}\nE-mail : {$email}\nTéléphone : " . ($phone !== '' ? $phone : 'Non fourni')
                        . "\n\nMessage :\n{$message}"
                );
            }
        });

        return true;
    }
}
