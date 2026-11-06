<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use PDO;

final class ReceptionService
{
    public function dashboard(int $userId): array
    {
        return [
            'queue' => $this->queue(),
            'recentPatients' => $this->recentPatients(),
            'recentInvoices' => $this->recentInvoices(),
            'todayAppointments' => $this->todayAppointments(),
            'pendingValidations' => $this->pendingAppointmentValidations(),
            'doctorAlerts' => $this->openDoctorAlerts(),
            'recentActions' => $this->recentActions($userId),
        ];
    }

    public function searchPatients(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $needle = '%' . $query . '%';
        $stmt = Database::pdo()->prepare('SELECT id, record_number, first_name, last_name, birth_date, sex, phone, email
            FROM patients
            WHERE deleted_at IS NULL
              AND (record_number LIKE :record_number OR first_name LIKE :first_name OR last_name LIKE :last_name OR phone LIKE :phone)
            ORDER BY last_name, first_name
            LIMIT ' . max(1, min(25, $limit)));
        $stmt->execute([
            'record_number' => $needle,
            'first_name' => $needle,
            'last_name' => $needle,
            'phone' => $needle,
        ]);

        return $stmt->fetchAll();
    }

    public function duplicatePatients(array $input): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, record_number, first_name, last_name, birth_date, phone
            FROM patients
            WHERE deleted_at IS NULL
              AND LOWER(last_name) = LOWER(:last_name)
              AND birth_date = :birth_date
              AND phone = :phone
            LIMIT 5');
        $stmt->execute([
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'birth_date' => (string) ($input['birth_date'] ?? ''),
            'phone' => trim((string) ($input['phone'] ?? '')),
        ]);

        return $stmt->fetchAll();
    }

    public function createPatient(array $input, int $actorUserId): int
    {
        $errors = $this->validatePatient($input);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        if ($this->duplicatePatients($input) !== []) {
            throw new \InvalidArgumentException('Doublon potentiel detecte : meme nom, date de naissance et telephone.');
        }

        $patientId = Database::transaction(function (PDO $pdo) use ($input): int {
            $stmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM patients');
            $nextId = (int) $stmt->fetchColumn();
            $recordNumber = sprintf('CLV-%s-%06d', date('Y'), $nextId);

            $stmt = $pdo->prepare('INSERT INTO patients
                (record_number, first_name, last_name, birth_date, sex, phone, email, address, emergency_contact, consent_at, created_at)
                VALUES (:record_number, :first_name, :last_name, :birth_date, :sex, :phone, :email, :address, :emergency_contact, :consent_at, NOW())');
            $stmt->execute([
                'record_number' => $recordNumber,
                'first_name' => trim((string) $input['first_name']),
                'last_name' => trim((string) $input['last_name']),
                'birth_date' => (string) $input['birth_date'],
                'sex' => (string) $input['sex'],
                'phone' => trim((string) $input['phone']),
                'email' => trim((string) ($input['email'] ?? '')) ?: null,
                'address' => trim((string) ($input['address'] ?? '')) ?: null,
                'emergency_contact' => trim((string) ($input['emergency_contact'] ?? '')) ?: null,
                'consent_at' => !empty($input['consent']) ? date('Y-m-d H:i:s') : null,
            ]);

            return (int) $pdo->lastInsertId();
        });
        (new AuditLogger())->log('reception_patient_created', 'patient', $patientId, $actorUserId);
        return $patientId;
    }

    public function recordTriage(array $input, int $userId): int
    {
        $patientId = (int) ($input['patient_id'] ?? 0);
        if ($patientId <= 0 || !$this->patientExists($patientId)) {
            throw new \InvalidArgumentException('Patient invalide.');
        }

        return Database::transaction(function (PDO $pdo) use ($input, $patientId, $userId): int {
            $appointmentId = $this->appointmentForPatientToday($patientId);

            $stmt = $pdo->prepare('INSERT INTO queue_triage
                (appointment_id, patient_id, priority, arrival_state, immediate_reason, arrived_at, created_at)
                VALUES (:appointment_id, :patient_id, :priority, :arrival_state, :immediate_reason, NOW(), NOW())');
            $stmt->execute([
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId,
                'priority' => (string) ($input['priority'] ?? 'normal'),
                'arrival_state' => trim((string) ($input['arrival_state'] ?? '')) ?: null,
                'immediate_reason' => trim((string) ($input['immediate_reason'] ?? '')) ?: null,
            ]);
            $queueId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO vitals
                (patient_id, appointment_id, temperature, systolic, diastolic, pulse, respiratory_rate, oxygen_saturation, weight_kg, height_cm, recorded_by, recorded_at)
                VALUES (:patient_id, :appointment_id, :temperature, :systolic, :diastolic, :pulse, :respiratory_rate, :oxygen_saturation, :weight_kg, :height_cm, :recorded_by, NOW())');
            $stmt->execute([
                'patient_id' => $patientId,
                'appointment_id' => $appointmentId,
                'temperature' => $this->nullableNumber($input['temperature'] ?? null),
                'systolic' => $this->nullableInt($input['systolic'] ?? null),
                'diastolic' => $this->nullableInt($input['diastolic'] ?? null),
                'pulse' => $this->nullableInt($input['pulse'] ?? null),
                'respiratory_rate' => $this->nullableInt($input['respiratory_rate'] ?? null),
                'oxygen_saturation' => $this->nullableInt($input['oxygen_saturation'] ?? null),
                'weight_kg' => $this->nullableNumber($input['weight_kg'] ?? null),
                'height_cm' => $this->nullableNumber($input['height_cm'] ?? null),
                'recorded_by' => $userId,
            ]);

            if ($appointmentId !== null) {
                $stmt = $pdo->prepare('UPDATE appointments SET status = "arrive", updated_at = NOW() WHERE id = :id AND status IN ("confirme", "en_attente_confirmation", "en_attente_validation", "reporte")');
                $stmt->execute(['id' => $appointmentId]);
            }

            (new AuditLogger())->log('reception_triage_recorded', 'patient', $patientId, $userId);
            return $queueId;
        });
    }

    public function queue(): array
    {
        $stmt = Database::pdo()->query('SELECT q.*, p.record_number, p.first_name, p.last_name, p.phone
            FROM queue_triage q
            INNER JOIN patients p ON p.id = q.patient_id
            WHERE q.completed_at IS NULL
            ORDER BY FIELD(q.priority, "urgence_vitale", "urgent", "normal", "non_urgent"), q.arrived_at ASC
            LIMIT 30');

        return $stmt->fetchAll();
    }

    public function doctors(): array
    {
        $stmt = Database::pdo()->query('SELECT d.id, d.slot_duration_minutes, u.first_name, u.last_name, s.name AS specialty
            FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN specialties s ON s.id = d.specialty_id
            WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL AND u.is_active = 1
            ORDER BY u.last_name, u.first_name');

        return $stmt->fetchAll();
    }

    public function schedules(): array
    {
        $stmt = Database::pdo()->query('SELECT ds.*, u.first_name, u.last_name
            FROM doctor_schedules ds
            INNER JOIN doctors d ON d.id = ds.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            ORDER BY u.last_name, ds.weekday, ds.start_time');

        return $stmt->fetchAll();
    }

    public function addSchedule(array $input, int $actorUserId): void
    {
        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $weekday = (int) ($input['weekday'] ?? 0);
        $start = (string) ($input['start_time'] ?? '');
        $end = (string) ($input['end_time'] ?? '');

        if ($doctorId <= 0 || $weekday < 1 || $weekday > 7 || $start === '' || $end === '' || $start >= $end) {
            throw new \InvalidArgumentException('Plage horaire invalide.');
        }

        $stmt = Database::pdo()->prepare('INSERT IGNORE INTO doctor_schedules (doctor_id, weekday, start_time, end_time, created_at)
            VALUES (:doctor_id, :weekday, :start_time, :end_time, NOW())');
        $stmt->execute(['doctor_id' => $doctorId, 'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end]);
        (new AuditLogger())->log('reception_doctor_schedule_added', 'doctor', $doctorId, $actorUserId);
    }

    public function addTimeOff(array $input, int $actorUserId): void
    {
        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $startsAt = str_replace('T', ' ', (string) ($input['starts_at'] ?? ''));
        $endsAt = str_replace('T', ' ', (string) ($input['ends_at'] ?? ''));

        if ($doctorId <= 0 || $startsAt === '' || $endsAt === '' || $startsAt >= $endsAt) {
            throw new \InvalidArgumentException('Conge ou exception invalide.');
        }

        $stmt = Database::pdo()->prepare('INSERT INTO doctor_time_off (doctor_id, starts_at, ends_at, reason, created_at)
            VALUES (:doctor_id, :starts_at, :ends_at, :reason, NOW())');
        $stmt->execute([
            'doctor_id' => $doctorId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => trim((string) ($input['reason'] ?? '')) ?: null,
        ]);
        (new AuditLogger())->log('reception_doctor_time_off_added', 'doctor', $doctorId, $actorUserId);
    }

    public function services(): array
    {
        $stmt = Database::pdo()->query('SELECT id, code, name, price_xaf FROM services WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name');
        return $stmt->fetchAll();
    }

    public function createInvoice(array $input, int $userId): int
    {
        $patientId = (int) ($input['patient_id'] ?? 0);
        $serviceIds = $input['service_ids'] ?? [];
        $quantities = $input['quantities'] ?? [];
        if ($patientId <= 0 || !$this->patientExists($patientId) || !is_array($serviceIds) || $serviceIds === []) {
            throw new \InvalidArgumentException('Patient et au moins un acte sont obligatoires.');
        }

        return Database::transaction(function (PDO $pdo) use ($patientId, $serviceIds, $quantities, $input, $userId): int {
            $number = 'FAC-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $pdo->prepare('INSERT INTO invoices (patient_id, invoice_number, status, total_xaf, issued_at, created_at)
                VALUES (:patient_id, :invoice_number, "emise", 0, NOW(), NOW())');
            $stmt->execute(['patient_id' => $patientId, 'invoice_number' => $number]);
            $invoiceId = (int) $pdo->lastInsertId();
            $total = 0;

            foreach ($serviceIds as $serviceId) {
                $service = $this->service((int) $serviceId);
                if ($service === null) {
                    continue;
                }

                $quantity = max(1, (int) ($quantities[$serviceId] ?? 1));
                $lineTotal = $quantity * (int) $service['price_xaf'];
                $total += $lineTotal;
                $stmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, service_id, label, quantity, unit_price_xaf, total_xaf)
                    VALUES (:invoice_id, :service_id, :label, :quantity, :unit_price_xaf, :total_xaf)');
                $stmt->execute([
                    'invoice_id' => $invoiceId,
                    'service_id' => $service['id'],
                    'label' => $service['name'],
                    'quantity' => $quantity,
                    'unit_price_xaf' => $service['price_xaf'],
                    'total_xaf' => $lineTotal,
                ]);
            }

            if ($total <= 0) {
                throw new \InvalidArgumentException('Aucun acte facturable valide.');
            }

            $status = !empty($input['mark_paid']) ? 'payee' : 'emise';
            $stmt = $pdo->prepare('UPDATE invoices SET total_xaf = :total, status = :status, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['total' => $total, 'status' => $status, 'id' => $invoiceId]);

            if ($status === 'payee') {
                $stmt = $pdo->prepare('INSERT INTO payments (invoice_id, amount_xaf, method, reference, received_by, paid_at)
                    VALUES (:invoice_id, :amount_xaf, :method, :reference, :received_by, NOW())');
                $stmt->execute([
                    'invoice_id' => $invoiceId,
                    'amount_xaf' => $total,
                    'method' => (string) ($input['payment_method'] ?? 'especes'),
                    'reference' => trim((string) ($input['payment_reference'] ?? '')) ?: null,
                    'received_by' => $userId,
                ]);
            }

            (new AuditLogger())->log('reception_invoice_created', 'invoice', $invoiceId, $userId);
            return $invoiceId;
        });
    }

    private function validatePatient(array $input): array
    {
        $errors = [];
        foreach (['first_name', 'last_name', 'birth_date', 'sex', 'phone'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[] = 'Le champ ' . $field . ' est obligatoire.';
            }
        }

        if (!in_array($input['sex'] ?? '', ['F', 'M', 'Autre'], true)) {
            $errors[] = 'Sexe invalide.';
        }

        if (($input['email'] ?? '') !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse e-mail invalide.';
        }

        return $errors;
    }

    private function patientExists(int $patientId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM patients WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $patientId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    private function appointmentForPatientToday(int $patientId)
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM appointments
            WHERE patient_id = :patient_id
              AND DATE(start_datetime) = CURDATE()
              AND status IN ("confirme", "en_attente_confirmation", "en_attente_validation", "reporte")
            ORDER BY start_datetime ASC LIMIT 1');
        $stmt->execute(['patient_id' => $patientId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function recentPatients(): array
    {
        $stmt = Database::pdo()->query('SELECT id, record_number, first_name, last_name, phone, created_at FROM patients WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 6');
        return $stmt->fetchAll();
    }

    private function recentInvoices(): array
    {
        $stmt = Database::pdo()->query('SELECT i.invoice_number, i.status, i.total_xaf, p.first_name, p.last_name
            FROM invoices i
            INNER JOIN patients p ON p.id = i.patient_id
            WHERE i.deleted_at IS NULL
            ORDER BY i.created_at DESC LIMIT 6');
        return $stmt->fetchAll();
    }

    private function todayAppointments(): array
    {
        $stmt = Database::pdo()->query('SELECT a.id, a.start_datetime, a.status, p.id AS patient_id, p.first_name, p.last_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            WHERE DATE(a.start_datetime) = CURDATE() AND a.deleted_at IS NULL
            ORDER BY a.start_datetime ASC LIMIT 10');
        return $stmt->fetchAll();
    }

    public function upcomingAppointments(): array
    {
        $stmt = Database::pdo()->query('SELECT a.id, a.start_datetime, a.status, a.reason, p.record_number, p.first_name, p.last_name, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            INNER JOIN doctors d ON d.id = a.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            WHERE a.start_datetime >= NOW() AND a.deleted_at IS NULL AND a.status IN ("en_attente_confirmation", "en_attente_validation", "reporte", "confirme", "arrive")
            ORDER BY a.start_datetime ASC LIMIT 20');

        return $stmt->fetchAll();
    }

    public function recentActions(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT action, entity_type, entity_id, ip_address, created_at
            FROM audit_logs WHERE user_id = :user_id
            ORDER BY created_at DESC LIMIT 30');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function openDoctorAlerts(): array
    {
        $stmt = Database::pdo()->query('SELECT al.id, al.message, al.created_at, al.status,
                u.first_name AS doctor_first_name, u.last_name AS doctor_last_name,
                a.id AS appointment_id, a.start_datetime,
                p.record_number, p.first_name AS patient_first_name, p.last_name AS patient_last_name
            FROM doctor_impediment_alerts al
            INNER JOIN doctors d ON d.id = al.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN appointments a ON a.id = al.appointment_id
            INNER JOIN patients p ON p.id = a.patient_id
            WHERE al.status = "nouvelle"
            ORDER BY al.created_at DESC LIMIT 50');
        return $stmt->fetchAll();
    }

    public function resolveDoctorAlert(int $alertId, int $userId): void
    {
        $stmt = Database::pdo()->prepare('UPDATE doctor_impediment_alerts
            SET status = "traitee", resolved_by = :resolved_by, resolved_at = NOW()
            WHERE id = :id AND status = "nouvelle"');
        $stmt->execute(['resolved_by' => $userId, 'id' => $alertId]);
        if ($stmt->rowCount() !== 1) {
            throw new \InvalidArgumentException('Cette alerte est introuvable ou déjà traitée.');
        }
        (new AuditLogger())->log('reception_doctor_alert_resolved', 'doctor_impediment_alert', $alertId, $userId);
    }

    public function pendingAppointmentValidations(): array
    {
        $stmt = Database::pdo()->query('SELECT a.id, a.start_datetime, a.validation_expires_at, a.reason,
                p.record_number, p.first_name, p.last_name, p.phone,
                u.first_name AS doctor_first_name, u.last_name AS doctor_last_name, s.name AS specialty
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            INNER JOIN doctors d ON d.id = a.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN specialties s ON s.id = a.specialty_id
            WHERE a.status = "en_attente_validation" AND a.deleted_at IS NULL
            ORDER BY a.start_datetime ASC LIMIT 50');

        return $stmt->fetchAll();
    }

    private function service(int $id)
    {
        $stmt = Database::pdo()->prepare('SELECT id, name, price_xaf FROM services WHERE id = :id AND is_active = 1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $service = $stmt->fetch();

        return is_array($service) ? $service : null;
    }

    private function nullableNumber($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInt($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : (int) $value;
    }
}
