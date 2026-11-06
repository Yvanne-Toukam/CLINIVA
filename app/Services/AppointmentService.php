<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use Cliniva\Models\Patient;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;

final class AppointmentService
{
    private $patients;
    private $notifications;

    public function __construct()
    {
        $this->patients = new Patient();
        $this->notifications = new NotificationService();
    }

    public function expirePendingAppointments(): void
    {
        $stmt = Database::pdo()->prepare('UPDATE appointments SET status = "expire", updated_at = NOW()
            WHERE (status = "en_attente_confirmation" AND confirmation_expires_at IS NOT NULL AND confirmation_expires_at < NOW())
               OR (status IN ("en_attente_validation", "reporte") AND validation_expires_at IS NOT NULL AND validation_expires_at < NOW())');
        $stmt->execute();
    }

    public function queueScheduledReminders(): int
    {
        if (!filter_var(env('APPOINTMENT_REMINDER_ENABLED', true), FILTER_VALIDATE_BOOL)) {
            return 0;
        }

        $pdo = Database::pdo();
        $queued = 0;
        $windows = [
            'j1' => ['start' => 23, 'end' => 25, 'unit' => 'HOUR', 'label' => 'demain'],
            'h2' => ['start' => 115, 'end' => 125, 'unit' => 'MINUTE', 'label' => 'dans environ deux heures'],
        ];

        foreach ($windows as $type => $window) {
            $stmt = $pdo->prepare('SELECT a.id AS appointment_id, a.start_datetime, p.id AS patient_id,
                    p.user_id, p.first_name, p.email
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                WHERE a.status = "confirme" AND a.deleted_at IS NULL
                    AND p.email IS NOT NULL AND p.email <> ""
                    AND a.start_datetime BETWEEN DATE_ADD(NOW(), INTERVAL ' . $window['start'] . ' ' . $window['unit'] . ')
                        AND DATE_ADD(NOW(), INTERVAL ' . $window['end'] . ' ' . $window['unit'] . ')');
            $stmt->execute();

            foreach ($stmt->fetchAll() as $appointment) {
                if (!filter_var((string) $appointment['email'], FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                Database::transaction(function (PDO $pdo) use ($appointment, $type, $window, &$queued): void {
                    $insert = $pdo->prepare('INSERT IGNORE INTO appointment_reminders (appointment_id, reminder_type, queued_at)
                        VALUES (:appointment_id, :reminder_type, NOW())');
                    $insert->execute([
                        'appointment_id' => (int) $appointment['appointment_id'],
                        'reminder_type' => $type,
                    ]);
                    if ($insert->rowCount() !== 1) {
                        return;
                    }

                    $when = date('d/m/Y à H:i', strtotime((string) $appointment['start_datetime']));
                    $this->notifications->queueReminderForPatient(
                        (int) $appointment['patient_id'],
                        (string) $appointment['email'],
                        'Rappel de rendez-vous Cliniva',
                        'Bonjour ' . $appointment['first_name'] . ', rappel : votre rendez-vous est prévu ' . $window['label'] . ', le ' . $when . '.',
                        $appointment['user_id'] === null ? null : (int) $appointment['user_id']
                    );
                    $queued++;
                });
            }
        }

        return $queued;
    }

    public function patientForCurrentUser()
    {
        $user = current_user();
        return $user === null ? null : $this->patients->findByUserId((int) $user['id']);
    }

    public function specialties(): array
    {
        $stmt = Database::pdo()->query('SELECT id, name, description FROM specialties WHERE deleted_at IS NULL ORDER BY name');
        return $stmt->fetchAll();
    }

    public function doctorsBySpecialty(int $specialtyId): array
    {
        $stmt = Database::pdo()->prepare('SELECT d.id, d.slot_duration_minutes, u.first_name, u.last_name, s.name AS specialty
            FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN specialties s ON s.id = d.specialty_id
            WHERE d.specialty_id = :specialty_id AND d.deleted_at IS NULL AND u.deleted_at IS NULL AND u.is_active = 1
            ORDER BY u.last_name, u.first_name');
        $stmt->execute(['specialty_id' => $specialtyId]);
        $doctors = $stmt->fetchAll();
        $scheduleStmt = Database::pdo()->prepare('SELECT weekday, start_time, end_time
            FROM doctor_schedules WHERE doctor_id = :doctor_id ORDER BY weekday, start_time');

        foreach ($doctors as &$doctor) {
            $scheduleStmt->execute(['doctor_id' => (int) $doctor['id']]);
            $doctor['availability'] = $scheduleStmt->fetchAll();
            $doctor['next_slots'] = $this->nextSlotsForDoctor((int) $doctor['id'], 3);
        }

        return $doctors;
    }

    public function availableSlots(int $doctorId, string $date): array
    {
        return $this->availableSlotsExceptAppointment($doctorId, $date, null);
    }

    public function availableSlotsForPatient(int $userId, int $doctorId, string $date, int $ignoreAppointmentId): array
    {
        if ($ignoreAppointmentId > 0) {
            $this->appointmentForPatientEdit($userId, $ignoreAppointmentId);
        }

        return $this->availableSlotsExceptAppointment($doctorId, $date, $ignoreAppointmentId > 0 ? $ignoreAppointmentId : null);
    }

    private function availableSlotsExceptAppointment(int $doctorId, string $date, ?int $ignoreAppointmentId): array
    {
        $timezone = new DateTimeZone((string) env('APP_TIMEZONE', 'Africa/Douala'));
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if (!$day instanceof DateTimeImmutable) {
            return [];
        }

        $today = new DateTimeImmutable('today', $timezone);
        if ($day < $today) {
            return [];
        }

        $doctor = $this->doctor($doctorId);
        if ($doctor === null) {
            return [];
        }

        $weekday = (int) $day->format('N');
        $stmt = Database::pdo()->prepare('SELECT start_time, end_time FROM doctor_schedules WHERE doctor_id = :doctor_id AND weekday = :weekday ORDER BY start_time');
        $stmt->execute(['doctor_id' => $doctorId, 'weekday' => $weekday]);
        $schedules = $stmt->fetchAll();
        if ($schedules === []) {
            return [];
        }

        $busy = $this->busyRanges($doctorId, $day->format('Y-m-d'), $ignoreAppointmentId);
        $timeOff = $this->timeOffRanges($doctorId, $day->format('Y-m-d'));
        $duration = new DateInterval('PT' . (int) $doctor['slot_duration_minutes'] . 'M');
        $now = new DateTimeImmutable('now', $timezone);
        $slots = [];

        foreach ($schedules as $schedule) {
            $cursor = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $schedule['start_time'], $timezone);
            $end = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $schedule['end_time'], $timezone);

            while ($cursor->add($duration) <= $end) {
                $slotEnd = $cursor->add($duration);
                if ($cursor > $now && !$this->overlaps($cursor, $slotEnd, $busy) && !$this->overlaps($cursor, $slotEnd, $timeOff)) {
                    $slots[] = [
                        'start' => $cursor->format('Y-m-d H:i:s'),
                        'end' => $slotEnd->format('Y-m-d H:i:s'),
                        'label' => $cursor->format('H:i') . ' - ' . $slotEnd->format('H:i'),
                    ];
                }

                $cursor = $slotEnd;
            }
        }

        return $slots;
    }

    /** @return array{appointment_id:int} */
    public function createPatientAppointment(int $userId, array $input): array
    {
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associe a ce compte.');
        }

        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $specialtyId = (int) ($input['specialty_id'] ?? 0);
        $start = (string) ($input['start_datetime'] ?? '');
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($doctorId <= 0 || $specialtyId <= 0 || $start === '' || $reason === '' || mb_strlen($reason) > 255) {
            throw new \InvalidArgumentException('Tous les champs du rendez-vous sont obligatoires.');
        }
        $doctor = $this->doctor($doctorId);
        if ($doctor === null || (int) $doctor['specialty_id'] !== $specialtyId) {
            throw new \InvalidArgumentException('Medecin ou specialite invalide.');
        }

        $date = substr($start, 0, 10);
        $slot = null;
        foreach ($this->availableSlots($doctorId, $date) as $availableSlot) {
            if ($availableSlot['start'] === $start) {
                $slot = $availableSlot;
                break;
            }
        }

        if ($slot === null) {
            throw new \InvalidArgumentException('Ce creneau n est plus disponible.');
        }

        return Database::transaction(function (PDO $pdo) use ($patient, $doctor, $specialtyId, $slot, $reason, $userId): array {
            $hours = max(1, (int) app_setting('appointment_validation_hours', 24));

            try {
                $stmt = $pdo->prepare('INSERT INTO appointments
                    (patient_id, doctor_id, specialty_id, start_datetime, end_datetime, status, reason, validation_expires_at, created_by, created_at)
                    VALUES (:patient_id, :doctor_id, :specialty_id, :start_datetime, :end_datetime, "en_attente_validation", :reason, DATE_ADD(NOW(), INTERVAL ' . $hours . ' HOUR), :created_by, NOW())');
                $stmt->execute([
                    'patient_id' => $patient['id'],
                    'doctor_id' => $doctor['id'],
                    'specialty_id' => $specialtyId,
                    'start_datetime' => $slot['start'],
                    'end_datetime' => $slot['end'],
                    'reason' => $reason,
                    'created_by' => $userId,
                ]);
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                    throw $exception;
                }
                throw new \InvalidArgumentException('Ce creneau vient d etre reserve. Choisissez un autre horaire.');
            }

            $appointmentId = (int) $pdo->lastInsertId();
            $this->notifySecretaries(
                $pdo,
                (int) $patient['id'],
                'Rendez-vous à valider',
                'Une demande de rendez-vous du patient ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' attend votre validation. Rendez-vous #' . $appointmentId . ', créneau : ' . $slot['start'] . '.'
            );

            return ['appointment_id' => $appointmentId];
        });
    }

    public function createStaffAppointment(int $patientId, array $input, int $createdBy): int
    {
        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $specialtyId = (int) ($input['specialty_id'] ?? 0);
        $start = (string) ($input['start_datetime'] ?? '');
        $reason = trim((string) ($input['reason'] ?? ''));

        if ($patientId <= 0 || $doctorId <= 0 || $specialtyId <= 0 || $start === '' || $reason === '') {
            throw new \InvalidArgumentException('Patient, medecin, creneau et motif sont obligatoires.');
        }

        $doctor = $this->doctor($doctorId);
        if ($doctor === null || (int) $doctor['specialty_id'] !== $specialtyId) {
            throw new \InvalidArgumentException('Medecin ou specialite invalide.');
        }

        $slot = null;
        foreach ($this->availableSlots($doctorId, substr($start, 0, 10)) as $availableSlot) {
            if ($availableSlot['start'] === $start) {
                $slot = $availableSlot;
                break;
            }
        }

        if ($slot === null) {
            throw new \InvalidArgumentException('Ce creneau n est plus disponible.');
        }

        $appointmentId = Database::transaction(function (PDO $pdo) use ($patientId, $doctor, $specialtyId, $slot, $reason, $createdBy): int {
            try {
                $stmt = $pdo->prepare('INSERT INTO appointments
                    (patient_id, doctor_id, specialty_id, start_datetime, end_datetime, status, reason, confirmed_at, created_by, created_at)
                    VALUES (:patient_id, :doctor_id, :specialty_id, :start_datetime, :end_datetime, "confirme", :reason, NOW(), :created_by, NOW())');
                $stmt->execute([
                    'patient_id' => $patientId,
                    'doctor_id' => $doctor['id'],
                    'specialty_id' => $specialtyId,
                    'start_datetime' => $slot['start'],
                    'end_datetime' => $slot['end'],
                    'reason' => $reason,
                    'created_by' => $createdBy,
                ]);
            } catch (PDOException) {
                throw new \InvalidArgumentException('Ce creneau vient d etre reserve. Choisissez un autre horaire.');
            }

            return (int) $pdo->lastInsertId();
        });
        (new AuditLogger())->log('reception_appointment_created', 'appointment', $appointmentId, $createdBy);
        return $appointmentId;
    }

    public function cancelStaffAppointment(int $appointmentId, int $actorUserId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE appointments
            SET status = "annule", cancelled_at = NOW(), updated_at = NOW()
            WHERE id = :id AND status IN ("en_attente_confirmation", "en_attente_validation", "reporte", "confirme", "arrive")');
        $stmt->execute(['id' => $appointmentId]);

        if ($stmt->rowCount() !== 1) {
            throw new \InvalidArgumentException('Annulation impossible pour ce rendez-vous.');
        }
        (new AuditLogger())->log('reception_appointment_cancelled', 'appointment', $appointmentId, $actorUserId);
    }

    public function validatePatientAppointment(int $appointmentId, int $staffUserId): void
    {
        Database::transaction(function (PDO $pdo) use ($appointmentId, $staffUserId): void {
            $appointment = $this->lockAppointmentForSecretary($pdo, $appointmentId, ['en_attente_validation']);
            $update = $pdo->prepare('UPDATE appointments SET status = "confirme", confirmed_at = NOW(), validation_expires_at = NULL, updated_at = NOW() WHERE id = :id');
            $update->execute(['id' => $appointmentId]);

            $this->notifyPatient($pdo, $appointment, $staffUserId, 'Rendez-vous confirmé', 'Votre rendez-vous du ' . date('d/m/Y à H:i', strtotime((string) $appointment['start_datetime'])) . ' est confirmé.');
            (new AuditLogger())->log('appointment_validated_by_secretary', 'appointment', $appointmentId, $staffUserId);
        });
    }

    public function refusePatientAppointment(int $appointmentId, int $staffUserId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new \InvalidArgumentException('Le motif du refus est obligatoire et limité à 500 caractères.');
        }

        Database::transaction(function (PDO $pdo) use ($appointmentId, $staffUserId, $reason): void {
            $appointment = $this->lockAppointmentForSecretary($pdo, $appointmentId, ['en_attente_validation']);
            $update = $pdo->prepare('UPDATE appointments SET status = "refuse", validation_expires_at = NULL, updated_at = NOW() WHERE id = :id');
            $update->execute(['id' => $appointmentId]);

            $this->notifyPatient($pdo, $appointment, $staffUserId, 'Demande de rendez-vous refusée', 'Votre demande de rendez-vous a été refusée. Motif : ' . $reason);
            (new AuditLogger())->log('appointment_refused_by_secretary', 'appointment', $appointmentId, $staffUserId);
        });
    }

    public function reschedulePatientAppointmentBySecretary(int $appointmentId, int $staffUserId, string $newStart): void
    {
        $newStart = str_replace('T', ' ', trim($newStart));
        if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?\z/', $newStart) !== 1) {
            throw new \InvalidArgumentException('Le nouveau créneau est invalide.');
        }
        if (strlen($newStart) === 16) {
            $newStart .= ':00';
        }

        Database::transaction(function (PDO $pdo) use ($appointmentId, $staffUserId, $newStart): void {
            $appointment = $this->lockAppointmentForSecretary($pdo, $appointmentId, ['en_attente_validation', 'confirme']);
            $slot = null;
            foreach ($this->availableSlotsExceptAppointment((int) $appointment['doctor_id'], substr($newStart, 0, 10), $appointmentId) as $availableSlot) {
                if ($availableSlot['start'] === $newStart) {
                    $slot = $availableSlot;
                    break;
                }
            }
            if ($slot === null) {
                throw new \InvalidArgumentException('Ce créneau n’est pas disponible pour ce médecin.');
            }

            $hours = max(1, (int) app_setting('appointment_validation_hours', 24));
            $update = $pdo->prepare('UPDATE appointments SET start_datetime = :start_at, end_datetime = :end_at,
                status = "reporte", validation_expires_at = DATE_ADD(NOW(), INTERVAL ' . $hours . ' HOUR), updated_at = NOW()
                WHERE id = :id');
            $update->execute(['start_at' => $slot['start'], 'end_at' => $slot['end'], 'id' => $appointmentId]);

            $appointment['start_datetime'] = $slot['start'];
            $this->notifyPatient($pdo, $appointment, $staffUserId, 'Proposition de report', 'La secrétaire vous propose le créneau du ' . date('d/m/Y à H:i', strtotime($slot['start'])) . '. Répondez depuis votre espace patient.');
            (new AuditLogger())->log('appointment_rescheduled_by_secretary', 'appointment', $appointmentId, $staffUserId);
        });
    }

    public function sendAppointmentMessage(int $appointmentId, int $staffUserId, string $subject, string $body): void
    {
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || mb_strlen($subject) > 190 || $body === '' || mb_strlen($body) > 4000) {
            throw new \InvalidArgumentException('Le sujet et le message sont obligatoires (190 et 4 000 caractères maximum).');
        }

        Database::transaction(function (PDO $pdo) use ($appointmentId, $staffUserId, $subject, $body): void {
            $appointment = $this->lockAppointmentForSecretary($pdo, $appointmentId, ['en_attente_validation', 'reporte', 'confirme']);
            $this->notifyPatient($pdo, $appointment, $staffUserId, $subject, $body);
            (new AuditLogger())->log('appointment_message_sent', 'appointment', $appointmentId, $staffUserId);
        });
    }

    public function respondToAppointment(int $userId, int $appointmentId, string $response): void
    {
        if (!in_array($response, ['confirm', 'decline'], true)) {
            throw new \InvalidArgumentException('Réponse au rendez-vous invalide.');
        }
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associé à ce compte.');
        }

        Database::transaction(function (PDO $pdo) use ($userId, $appointmentId, $response, $patient): void {
            $stmt = $pdo->prepare('SELECT a.id, a.patient_id, a.status, a.start_datetime, p.user_id, p.email, p.first_name, p.last_name
                FROM appointments a INNER JOIN patients p ON p.id = a.patient_id
                WHERE a.id = :id AND a.patient_id = :patient_id AND a.deleted_at IS NULL
                    AND a.status IN ("reporte", "confirme") AND a.start_datetime >= NOW() FOR UPDATE');
            $stmt->execute(['id' => $appointmentId, 'patient_id' => (int) $patient['id']]);
            $appointment = $stmt->fetch();
            if (!is_array($appointment)) {
                throw new \InvalidArgumentException('Ce rendez-vous ne peut pas recevoir de réponse.');
            }

            $declined = $response === 'decline';
            if ($declined) {
                $update = $pdo->prepare('UPDATE appointments SET status = "annule", cancelled_at = NOW(), validation_expires_at = NULL, updated_at = NOW() WHERE id = :id');
                $update->execute(['id' => $appointmentId]);
            } elseif ($appointment['status'] === 'reporte') {
                $update = $pdo->prepare('UPDATE appointments SET status = "confirme", confirmed_at = NOW(), validation_expires_at = NULL, updated_at = NOW() WHERE id = :id');
                $update->execute(['id' => $appointmentId]);
            }

            $reply = $declined ? 'Le patient ne viendra pas au rendez-vous.' : 'Le patient confirme sa présence au rendez-vous.';
            $this->insertPatientMessage($pdo, (int) $patient['id'], $appointmentId, $userId, 'Réponse du patient', $reply);
            $this->notifySecretaries($pdo, (int) $patient['id'], 'Réponse du patient au rendez-vous', $reply . ' Rendez-vous #' . $appointmentId . '.');
            (new AuditLogger())->log('patient_appointment_response', 'appointment', $appointmentId, $userId);
        });
    }

    /** @param array<int, string> $allowedStatuses */
    private function lockAppointmentForSecretary(PDO $pdo, int $appointmentId, array $allowedStatuses): array
    {
        $placeholders = implode(', ', array_fill(0, count($allowedStatuses), '?'));
        $stmt = $pdo->prepare('SELECT a.id, a.patient_id, a.doctor_id, a.status, a.start_datetime,
                p.user_id, p.email, p.first_name, p.last_name
            FROM appointments a INNER JOIN patients p ON p.id = a.patient_id
            WHERE a.id = ? AND a.deleted_at IS NULL AND a.status IN (' . $placeholders . ') FOR UPDATE');
        $stmt->execute(array_merge([$appointmentId], $allowedStatuses));
        $appointment = $stmt->fetch();
        if (!is_array($appointment)) {
            throw new \InvalidArgumentException('Ce rendez-vous ne peut pas être traité dans son état actuel.');
        }

        return $appointment;
    }

    private function notifyPatient(PDO $pdo, array $appointment, int $senderId, string $subject, string $body): void
    {
        $this->insertPatientMessage($pdo, (int) $appointment['patient_id'], (int) $appointment['id'], $senderId, $subject, $body);
        $email = (string) ($appointment['email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $userId = isset($appointment['user_id']) ? (int) $appointment['user_id'] : null;
            $this->notifications->queueEmail($userId, (int) $appointment['patient_id'], $email, $subject, $body);
        }
    }

    private function insertPatientMessage(PDO $pdo, int $patientId, int $appointmentId, int $senderId, string $subject, string $body): void
    {
        $stmt = $pdo->prepare('INSERT INTO patient_messages (patient_id, appointment_id, sender_id, canal, sujet, corps, created_at)
            VALUES (:patient_id, :appointment_id, :sender_id, "espace", :subject, :body, NOW())');
        $stmt->execute([
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'sender_id' => $senderId,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    public function confirmAppointment(int $appointmentId, string $token): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE appointments
            SET status = "confirme", confirmed_at = NOW(), updated_at = NOW()
            WHERE id = :id AND status = "en_attente_confirmation" AND confirmation_token_hash = :token_hash AND confirmation_expires_at > NOW()');
        $stmt->execute(['id' => $appointmentId, 'token_hash' => hash_token($token)]);

        return $stmt->rowCount() === 1;
    }

    public function cancelPatientAppointment(int $userId, int $appointmentId): void
    {
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associe a ce compte.');
        }

        Database::transaction(function (PDO $pdo) use ($userId, $appointmentId, $patient): void {
            $hours = max(0, (int) app_setting('appointment_cancel_min_hours', 6));
            $stmt = $pdo->prepare('UPDATE appointments
                SET status = "annule", cancelled_at = NOW(), updated_at = NOW()
                WHERE id = :id
                  AND patient_id = :patient_id
                                    AND status IN ("en_attente_confirmation", "en_attente_validation", "confirme", "reporte")
                  AND start_datetime >= DATE_ADD(NOW(), INTERVAL ' . $hours . ' HOUR)');
            $stmt->execute(['id' => $appointmentId, 'patient_id' => $patient['id']]);

            if ($stmt->rowCount() !== 1) {
                throw new \InvalidArgumentException('Annulation impossible : vérifiez le délai minimum et le statut du rendez-vous.');
            }

            $this->notifySecretaries($pdo, (int) $patient['id'], 'Annulation demandée par un patient', 'Le patient ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' a annulé le rendez-vous #' . $appointmentId . '.');
            (new AuditLogger())->log('patient_appointment_cancelled', 'appointment', $appointmentId, $userId);
        });
    }

    public function appointmentForPatientEdit(int $userId, int $appointmentId): array
    {
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associé à ce compte.');
        }
        $stmt = Database::pdo()->prepare('SELECT a.id, a.doctor_id, a.specialty_id, a.start_datetime, a.reason, a.status,
                s.name AS specialty, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
            FROM appointments a
            INNER JOIN specialties s ON s.id = a.specialty_id
            INNER JOIN doctors d ON d.id = a.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            WHERE a.id = :id AND a.patient_id = :patient_id AND a.deleted_at IS NULL
                AND a.status IN ("en_attente_confirmation", "en_attente_validation", "reporte", "confirme")
                AND a.start_datetime >= DATE_ADD(NOW(), INTERVAL ' . max(0, (int) app_setting('appointment_cancel_min_hours', 6)) . ' HOUR)
            LIMIT 1');
        $stmt->execute(['id' => $appointmentId, 'patient_id' => (int) $patient['id']]);
        $appointment = $stmt->fetch();
        if (!is_array($appointment)) {
            throw new \InvalidArgumentException('Ce rendez-vous ne peut pas être modifié selon son statut ou le délai applicable.');
        }

        return $appointment;
    }

    public function reschedulePatientAppointment(int $userId, int $appointmentId, array $input): void
    {
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associé à ce compte.');
        }

        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $specialtyId = (int) ($input['specialty_id'] ?? 0);
        $start = (string) ($input['start_datetime'] ?? '');
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($doctorId <= 0 || $specialtyId <= 0 || $start === '' || $reason === '' || mb_strlen($reason) > 255) {
            throw new \InvalidArgumentException('Tous les champs du rendez-vous sont obligatoires.');
        }
        $doctor = $this->doctor($doctorId);
        if ($doctor === null || (int) $doctor['specialty_id'] !== $specialtyId) {
            throw new \InvalidArgumentException('Médecin ou spécialité invalide.');
        }

        Database::transaction(function (PDO $pdo) use ($userId, $appointmentId, $patient, $doctor, $doctorId, $specialtyId, $start, $reason): void {
            $lock = $pdo->prepare('SELECT id, status, start_datetime FROM appointments
                WHERE id = :id AND patient_id = :patient_id AND deleted_at IS NULL
                    AND status IN ("en_attente_confirmation", "en_attente_validation", "reporte", "confirme")
                    AND start_datetime >= DATE_ADD(NOW(), INTERVAL ' . max(0, (int) app_setting('appointment_cancel_min_hours', 6)) . ' HOUR)
                FOR UPDATE');
            $lock->execute(['id' => $appointmentId, 'patient_id' => (int) $patient['id']]);
            $current = $lock->fetch();
            if (!is_array($current)) {
                throw new \InvalidArgumentException('Ce rendez-vous ne peut pas être modifié selon son statut ou le délai applicable.');
            }

            $slot = null;
            foreach ($this->availableSlotsExceptAppointment($doctorId, substr($start, 0, 10), $appointmentId) as $availableSlot) {
                if ($availableSlot['start'] === $start) {
                    $slot = $availableSlot;
                    break;
                }
            }
            if ($slot === null) {
                throw new \InvalidArgumentException('Ce créneau n’est plus disponible.');
            }

            $validationHours = max(1, (int) app_setting('appointment_validation_hours', 24));
            try {
                $update = $pdo->prepare('UPDATE appointments SET doctor_id = :doctor_id, specialty_id = :specialty_id,
                    start_datetime = :start_datetime, end_datetime = :end_datetime, reason = :reason,
                    status = "en_attente_validation", confirmation_token_hash = NULL,
                    confirmation_expires_at = NULL, validation_expires_at = DATE_ADD(NOW(), INTERVAL ' . $validationHours . ' HOUR),
                    confirmed_at = NULL, updated_at = NOW()
                    WHERE id = :id AND patient_id = :patient_id');
                $update->execute([
                    'doctor_id' => $doctor['id'],
                    'specialty_id' => $specialtyId,
                    'start_datetime' => $slot['start'],
                    'end_datetime' => $slot['end'],
                    'reason' => $reason,
                    'id' => $appointmentId,
                    'patient_id' => (int) $patient['id'],
                ]);
            } catch (PDOException $exception) {
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                    throw $exception;
                }
                throw new \InvalidArgumentException('Ce créneau vient d’être réservé. Choisissez un autre horaire.');
            }

            $this->notifySecretaries($pdo, (int) $patient['id'], 'Modification de rendez-vous à valider', 'Le patient ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' demande le créneau du ' . $slot['start'] . ' pour le rendez-vous #' . $appointmentId . '.');
            (new AuditLogger())->log('patient_appointment_rescheduled', 'appointment', $appointmentId, $userId);
        });
    }

    public function patientDashboard(int $userId): array
    {
        $this->expirePendingAppointments();
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associe a ce compte.');
        }
        (new AuditLogger())->log('patient_medical_record_view', 'patient', (int) $patient['id'], $userId);

        return [
            'patient' => $patient,
            'allergies' => $this->patients->allergies((int) $patient['id']),
            'upcoming' => $this->appointmentsForPatient((int) $patient['id'], true),
            'history' => $this->appointmentsForPatient((int) $patient['id'], false),
            'consultations' => $this->consultationsForPatient((int) $patient['id']),
            'examResults' => $this->examResultsForPatient((int) $patient['id']),
            'prescriptions' => $this->prescriptionsForPatient((int) $patient['id']),
            'certificates' => $this->certificatesForPatient((int) $patient['id']),
            'documents' => $this->documentsForPatient((int) $patient['id']),
            'invoices' => $this->invoicesForPatient((int) $patient['id']),
        ];
    }

    public function upcomingAppointmentsForUser(int $userId): array
    {
        $patient = $this->patients->findByUserId($userId);
        if ($patient === null) {
            throw new \RuntimeException('Aucun dossier patient associé à ce compte.');
        }

        return $this->appointmentsForPatient((int) $patient['id'], true);
    }

    private function doctor(int $doctorId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT d.*, u.first_name, u.last_name, u.email
            FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            WHERE d.id = :id AND d.deleted_at IS NULL AND u.is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $doctorId]);
        $doctor = $stmt->fetch();

        return is_array($doctor) ? $doctor : null;
    }

    private function nextSlotsForDoctor(int $doctorId, int $limit): array
    {
        $timezone = new DateTimeZone((string) env('APP_TIMEZONE', 'Africa/Douala'));
        $cursor = new DateTimeImmutable('today', $timezone);
        $slots = [];

        for ($i = 0; $i < 21 && count($slots) < $limit; $i++) {
            foreach ($this->availableSlots($doctorId, $cursor->format('Y-m-d')) as $slot) {
                $slots[] = $slot;
                if (count($slots) >= $limit) {
                    break;
                }
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $slots;
    }

    private function busyRanges(int $doctorId, string $date, ?int $ignoreAppointmentId = null): array
    {
        $sql = 'SELECT start_datetime AS starts_at, end_datetime AS ends_at FROM appointments
            WHERE doctor_id = :doctor_id
              AND DATE(start_datetime) = :date
              AND status IN ("en_attente_confirmation", "en_attente_validation", "confirme", "reporte", "arrive", "en_consultation")';
        $params = ['doctor_id' => $doctorId, 'date' => $date];
        if ($ignoreAppointmentId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreAppointmentId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function notifySecretaries(PDO $pdo, int $patientId, string $subject, string $body): void
    {
        $stmt = $pdo->query('SELECT id, email FROM users WHERE role = "secretaire" AND is_active = 1 AND deleted_at IS NULL AND email IS NOT NULL AND email <> ""');
        $secretaries = $stmt->fetchAll();
        foreach ($secretaries as $secretary) {
            $this->notifications->queueEmail((int) $secretary['id'], $patientId, (string) $secretary['email'], $subject, $body);
        }
    }

    private function timeOffRanges(int $doctorId, string $date): array
    {
        $stmt = Database::pdo()->prepare('SELECT starts_at, ends_at FROM doctor_time_off
            WHERE doctor_id = :doctor_id AND DATE(starts_at) <= :date_start AND DATE(ends_at) >= :date_end');
        $stmt->execute(['doctor_id' => $doctorId, 'date_start' => $date, 'date_end' => $date]);

        return $stmt->fetchAll();
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $ranges): bool
    {
        foreach ($ranges as $range) {
            $rangeStart = new DateTimeImmutable((string) $range['starts_at']);
            $rangeEnd = new DateTimeImmutable((string) $range['ends_at']);
            if ($start < $rangeEnd && $end > $rangeStart) {
                return true;
            }
        }

        return false;
    }

    private function appointmentsForPatient(int $patientId, bool $upcoming): array
    {
        $operator = $upcoming ? '>=' : '<';
        $direction = $upcoming ? 'ASC' : 'DESC';
        $statusFilter = $upcoming ? ' AND a.status IN ("en_attente_confirmation", "en_attente_validation", "confirme", "reporte", "arrive", "en_consultation")' : '';
        $stmt = Database::pdo()->prepare('SELECT a.*, s.name AS specialty, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
            FROM appointments a
            INNER JOIN specialties s ON s.id = a.specialty_id
            INNER JOIN doctors d ON d.id = a.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            WHERE a.patient_id = :patient_id AND a.deleted_at IS NULL AND a.start_datetime ' . $operator . ' NOW()' . $statusFilter . '
            ORDER BY a.start_datetime ' . $direction . ' LIMIT 10');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function consultationsForPatient(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT c.id, c.motive, c.icd10_code, c.created_at, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
            FROM consultations c
            INNER JOIN doctors d ON d.id = c.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            WHERE c.patient_id = :patient_id AND c.deleted_at IS NULL
            ORDER BY c.created_at DESC LIMIT 5');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function documentsForPatient(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, original_name, category, created_at FROM documents WHERE patient_id = :patient_id AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function examResultsForPatient(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT er.id, er.validated_at, er.created_at, req.label, req.type
            FROM exam_results er
            INNER JOIN exam_requests req ON req.id = er.exam_request_id
            INNER JOIN consultations c ON c.id = req.consultation_id
            WHERE c.patient_id = :patient_id
            ORDER BY COALESCE(er.validated_at, er.created_at) DESC LIMIT 5');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function prescriptionsForPatient(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT p.id, p.prescription_number, p.created_at
            FROM prescriptions p
            INNER JOIN consultations c ON c.id = p.consultation_id
            WHERE c.patient_id = :patient_id
            ORDER BY p.created_at DESC LIMIT 5');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function certificatesForPatient(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT cert.id, cert.certificate_number, cert.type, cert.created_at
            FROM certificates cert
            INNER JOIN consultations c ON c.id = cert.consultation_id
            WHERE c.patient_id = :patient_id
            ORDER BY cert.created_at DESC LIMIT 5');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function invoicesForPatient(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT invoice_number, status, total_xaf, issued_at, created_at FROM invoices WHERE patient_id = :patient_id AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }
}
