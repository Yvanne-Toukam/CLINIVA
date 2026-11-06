<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class NotificationService
{
    public function queueEmail($userId, $patientId, string $recipient, string $subject, string $body, ?string $scheduledAt = null): void
    {
        $scheduled = $scheduledAt ?? date('Y-m-d H:i:s');

        $sql = 'INSERT INTO notifications (user_id, patient_id, channel, subject, recipient, body, status, scheduled_at, created_at)
                VALUES (:user_id, :patient_id, "email", :subject, :recipient, :body, "en_attente", :scheduled_at, NOW())';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'patient_id' => $patientId,
            'subject' => $subject,
            'recipient' => $recipient,
            'body' => $body,
            'scheduled_at' => $scheduled,
        ]);
    }

    public function sendPendingEmails(): int
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM notifications
            WHERE channel = "email" AND status = "en_attente" AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            ORDER BY scheduled_at ASC, id ASC LIMIT 50');
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $count = 0;

        foreach ($rows as $row) {
            $ok = $this->sendEmail((string) ($row['recipient'] ?? ''), (string) ($row['subject'] ?? 'Cliniva'), (string) ($row['body'] ?? ''));

            $attempts = (int) $row['attempts'] + 1;
            $maxAttempts = max(1, (int) env('MAIL_MAX_ATTEMPTS', 5));
            $retry = !$ok && $attempts < $maxAttempts;
            $status = $ok ? 'envoye' : ($retry ? 'en_attente' : 'echec');
            $sentAt = $ok ? date('Y-m-d H:i:s') : null;
            $error = $ok ? null : 'Echec d\'envoi email';
            $scheduledAt = $retry
                ? date('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** max(0, $attempts - 1))))
                : $row['scheduled_at'];

            $update = Database::pdo()->prepare('UPDATE notifications SET status = :status, sent_at = :sent_at, scheduled_at = :scheduled_at, attempts = :attempts, last_error = :last_error WHERE id = :id');
            $update->execute([
                'status' => $status,
                'sent_at' => $sentAt,
                'scheduled_at' => $scheduledAt,
                'attempts' => $attempts,
                'last_error' => $error,
                'id' => (int) $row['id'],
            ]);

            if ($ok) {
                $count++;
            }
        }

        return $count;
    }

    public function queueReminderForPatient(int $patientId, string $recipient, string $subject, string $body, ?int $userId = null, ?string $scheduledAt = null): void
    {
        $this->queueEmail($userId, $patientId, $recipient, $subject, $body, $scheduledAt);
    }

    private function sendEmail(string $recipient, string $subject, string $body): bool
    {
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $host = (string) env('MAIL_HOST', '');
            $port = (int) env('MAIL_PORT', 0);
            $username = (string) env('MAIL_USERNAME', '');
            $password = (string) env('MAIL_PASSWORD', '');
            $security = (string) env('MAIL_ENCRYPTION', '');
            $fromAddress = (string) env('MAIL_FROM_ADDRESS', 'noreply@cliniva.local');
            $fromName = (string) env('MAIL_FROM_NAME', 'Cliniva');

            if ($host === '' || $host === 'localhost') {
                $headers = "From: {$fromName} <{$fromAddress}>\r\n"
                    . "Reply-To: {$fromAddress}\r\n"
                    . "MIME-Version: 1.0\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n";

                return mail($recipient, $subject, $body, $headers);
            }

            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();

            $smtpAuth = filter_var(env('MAIL_SMTP_AUTH', false), FILTER_VALIDATE_BOOL);

            $mail->Host = $host;
            $mail->Port = $port > 0 ? $port : 25;
            $mail->SMTPAuth = $smtpAuth;
            $mail->Username = $username;
            $mail->Password = $password;

            if ($security !== '') {
                $mail->SMTPSecure = $security;
            }

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->Body = nl2br($body, false);
            $mail->AltBody = $body;

            $mail->send();
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }
}
