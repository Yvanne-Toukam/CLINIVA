<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use PDO;
use PDOException;

final class DoctorService
{
    private $audit;

    public function __construct()
    {
        $this->audit = new AuditLogger();
    }

    public function doctorForUser(int $userId)
    {
        $stmt = Database::pdo()->prepare('SELECT d.*, u.first_name, u.last_name, s.name AS specialty
            FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN specialties s ON s.id = d.specialty_id
            WHERE d.user_id = :user_id AND d.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $doctor = $stmt->fetch();

        return is_array($doctor) ? $doctor : null;
    }

    public function dashboard(int $userId): array
    {
        $doctor = $this->requireDoctor($userId);
        $this->closeExpiredConsultations((int) $doctor['id']);

        return [
            'doctor' => $doctor,
            'appointments' => $this->appointmentsForDate((int) $doctor['id'], date('Y-m-d')),
            'queue' => $this->queueForDoctor((int) $doctor['id']),
            'activeConsultations' => $this->activeConsultations((int) $doctor['id']),
            'stats' => $this->stats((int) $doctor['id']),
            'consultationTrend' => $this->consultationTrend((int) $doctor['id']),
        ];
    }

    public function appointmentsForDate(int $doctorId, string $date): array
    {
        $stmt = Database::pdo()->prepare('SELECT a.*, p.record_number, p.first_name, p.last_name, p.birth_date, p.sex, p.phone
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            WHERE a.doctor_id = :doctor_id AND DATE(a.start_datetime) = :date AND a.deleted_at IS NULL
            ORDER BY a.start_datetime ASC');
        $stmt->execute(['doctor_id' => $doctorId, 'date' => $date]);

        return $stmt->fetchAll();
    }

    public function filteredAppointments(int $userId, string $date, string $status): array
    {
        $doctor = $this->requireDoctor($userId);
        $allowedStatuses = ['en_attente_validation', 'reporte', 'confirme', 'arrive', 'en_consultation', 'termine', 'annule', 'absent'];
        if ($date !== '' && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            throw new \InvalidArgumentException('Date de filtre invalide.');
        }
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Statut de filtre invalide.');
        }

        $conditions = ['a.doctor_id = :doctor_id', 'a.deleted_at IS NULL'];
        $params = ['doctor_id' => (int) $doctor['id']];
        if ($date !== '') {
            $conditions[] = 'DATE(a.start_datetime) = :appointment_date';
            $params['appointment_date'] = $date;
        }
        if ($status !== '') {
            $conditions[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $stmt = Database::pdo()->prepare('SELECT a.id, a.start_datetime, a.status, a.reason,
                p.id AS patient_id, p.record_number, p.first_name, p.last_name,
                s.name AS specialty
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            INNER JOIN specialties s ON s.id = a.specialty_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY a.start_datetime DESC LIMIT 100');
        $stmt->execute($params);

        return ['doctor' => $doctor, 'appointments' => $stmt->fetchAll(), 'dateFilter' => $date, 'statusFilter' => $status];
    }

    public function calendarEvents(int $userId, string $start, string $end): array
    {
        $doctor = $this->requireDoctor($userId);
        $stmt = Database::pdo()->prepare('SELECT a.id, a.start_datetime, a.end_datetime, a.status, p.first_name, p.last_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            WHERE a.doctor_id = :doctor_id
              AND a.deleted_at IS NULL
              AND a.start_datetime >= :start_at
              AND a.start_datetime <= :end_at
            ORDER BY a.start_datetime ASC');
        $stmt->execute([
            'doctor_id' => $doctor['id'],
            'start_at' => $start !== '' ? $start : date('Y-m-d 00:00:00'),
            'end_at' => $end !== '' ? $end : date('Y-m-d 23:59:59', strtotime('+30 days')),
        ]);

        return array_map(static function (array $appointment): array {
            return [
                'id' => (string) $appointment['id'],
                'title' => $appointment['first_name'] . ' ' . $appointment['last_name'] . ' - ' . $appointment['status'],
                'start' => str_replace(' ', 'T', (string) $appointment['start_datetime']),
                'end' => str_replace(' ', 'T', (string) $appointment['end_datetime']),
                'className' => 'status-' . $appointment['status'],
            ];
        }, $stmt->fetchAll());
    }

    public function moveAppointment(int $userId, int $appointmentId, string $start, string $end): void
    {
        $doctor = $this->requireDoctor($userId);
        if ($start === '' || $end === '' || $start >= $end) {
            throw new \InvalidArgumentException('Horaire invalide.');
        }

        $appointment = $this->appointmentForDoctor((int) $doctor['id'], $appointmentId);
        if ($appointment === null) {
            throw new \InvalidArgumentException('Rendez-vous inaccessible.');
        }

        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM appointments
            WHERE doctor_id = :doctor_id
              AND id <> :id
              AND status IN ("en_attente_confirmation", "en_attente_validation", "reporte", "confirme", "arrive", "en_consultation")
              AND start_datetime < :end_at
              AND end_datetime > :start_at');
        $stmt->execute([
            'doctor_id' => $doctor['id'],
            'id' => $appointmentId,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Conflit avec un autre rendez-vous.');
        }

        try {
            $stmt = Database::pdo()->prepare('UPDATE appointments SET start_datetime = :start_at, end_datetime = :end_at, updated_at = NOW()
                WHERE id = :id AND doctor_id = :doctor_id');
            $stmt->execute(['start_at' => $start, 'end_at' => $end, 'id' => $appointmentId, 'doctor_id' => $doctor['id']]);
        } catch (PDOException) {
            throw new \InvalidArgumentException('Conflit de reservation.');
        }
    }

    public function signalAppointmentImpediment(int $userId, int $appointmentId, string $message): int
    {
        $doctor = $this->requireDoctor($userId);
        $appointment = $this->appointmentForDoctor((int) $doctor['id'], $appointmentId);
        $message = trim($message);
        if ($appointment === null
            || !in_array($appointment['status'], ['en_attente_validation', 'reporte', 'confirme'], true)
            || strtotime((string) $appointment['start_datetime']) < time()) {
            throw new \InvalidArgumentException('Rendez-vous indisponible pour ce signalement.');
        }
        if ($message === '' || mb_strlen($message) > 1000) {
            throw new \InvalidArgumentException('Le motif est obligatoire et limité à 1 000 caractères.');
        }

        return Database::transaction(function (PDO $pdo) use ($doctor, $appointment, $userId, $message): int {
            $stmt = $pdo->prepare('INSERT INTO doctor_impediment_alerts (doctor_id, appointment_id, created_by, message, status, created_at)
                VALUES (:doctor_id, :appointment_id, :created_by, :message, "nouvelle", NOW())');
            $stmt->execute([
                'doctor_id' => (int) $doctor['id'],
                'appointment_id' => (int) $appointment['id'],
                'created_by' => $userId,
                'message' => $message,
            ]);
            $alertId = (int) $pdo->lastInsertId();

            $secretaries = $pdo->query('SELECT id, email FROM users WHERE role = "secretaire" AND is_active = 1
                AND deleted_at IS NULL AND email IS NOT NULL AND email <> ""')->fetchAll();
            $notification = new NotificationService();
            $body = 'Dr ' . $doctor['first_name'] . ' ' . $doctor['last_name']
                . ' signale un empêchement pour le rendez-vous #' . $appointment['id']
                . ' prévu le ' . date('d/m/Y à H:i', strtotime((string) $appointment['start_datetime']))
                . '. Motif : ' . $message;
            foreach ($secretaries as $secretary) {
                $notification->queueEmail((int) $secretary['id'], null, (string) $secretary['email'], 'Empêchement médecin à traiter', $body);
            }

            $this->audit->log('doctor_impediment_reported', 'doctor_impediment_alert', $alertId, $userId);
            return $alertId;
        });
    }

    public function medicalRecord(int $userId, int $patientId): array
    {
        $doctor = $this->requireDoctor($userId);
        if (!$this->canAccessPatient((int) $doctor['id'], $patientId)) {
            throw new \InvalidArgumentException('Dossier patient inaccessible.');
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM patients WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $patientId]);
        $patient = $stmt->fetch();
        if (!is_array($patient)) {
            throw new \InvalidArgumentException('Patient introuvable.');
        }

        $this->audit->log('medical_record_view', 'patient', $patientId, $userId);

        return [
            'doctor' => $doctor,
            'patient' => $patient,
            'allergies' => $this->allergies($patientId),
            'history' => $this->history($patientId),
            'vitals' => $this->vitals($patientId),
            'consultations' => $this->consultations($patientId),
            'documents' => $this->documents($patientId),
            'examResults' => $this->examResults($patientId),
        ];
    }

    public function startConsultation(int $userId, int $appointmentId): int
    {
        $doctor = $this->requireDoctor($userId);
        $this->closeExpiredConsultations((int) $doctor['id']);
        $appointment = $this->appointmentForDoctor((int) $doctor['id'], $appointmentId);
        if ($appointment === null) {
            throw new \InvalidArgumentException('Rendez-vous inaccessible.');
        }

        return Database::transaction(function (PDO $pdo) use ($doctor, $appointment): int {
            $stmt = $pdo->prepare('SELECT id FROM doctors WHERE id = :doctor_id FOR UPDATE');
            $stmt->execute(['doctor_id' => (int) $doctor['id']]);
            if ($stmt->fetchColumn() === false) {
                throw new \InvalidArgumentException('Profil médecin inaccessible.');
            }

            $stmt = $pdo->prepare('SELECT start_datetime FROM appointments
                WHERE id = :id AND doctor_id = :doctor_id AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([
                'id' => (int) $appointment['id'],
                'doctor_id' => (int) $doctor['id'],
            ]);
            $appointmentStart = $stmt->fetchColumn();
            if ($appointmentStart === false) {
                throw new \InvalidArgumentException('Rendez-vous inaccessible.');
            }
            if (strtotime((string) $appointmentStart) > time()) {
                throw new \InvalidArgumentException('Vous ne pouvez pas démarrer ce rendez-vous avant l’heure prévue.');
            }

            $stmt = $pdo->prepare('SELECT id FROM consultations
                WHERE appointment_id = :appointment_id AND doctor_id = :doctor_id AND deleted_at IS NULL
                ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $stmt->execute([
                'appointment_id' => (int) $appointment['id'],
                'doctor_id' => (int) $doctor['id'],
            ]);
            $existingConsultationId = $stmt->fetchColumn();
            if ($existingConsultationId !== false) {
                return (int) $existingConsultationId;
            }

            if (strtotime((string) $appointmentStart) <= time() - 1800) {
                throw new \InvalidArgumentException('Le délai de 30 minutes après l’heure prévue est écoulé. Ce rendez-vous ne peut plus être démarré.');
            }

            $stmt = $pdo->prepare('SELECT id FROM consultations
                WHERE doctor_id = :doctor_id AND locked_at IS NULL AND deleted_at IS NULL
                ORDER BY created_at ASC LIMIT 1');
            $stmt->execute(['doctor_id' => (int) $doctor['id']]);
            if ($stmt->fetchColumn() !== false) {
                throw new \InvalidArgumentException("Vous avez déjà une consultation en cours. Veuillez la clôturer avant d'en commencer une autre.");
            }

            $stmt = $pdo->prepare('UPDATE appointments SET status = "en_consultation", updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $appointment['id']]);

            $stmt = $pdo->prepare('INSERT INTO consultations (appointment_id, patient_id, doctor_id, motive, created_at)
                VALUES (:appointment_id, :patient_id, :doctor_id, :motive, NOW())');
            $stmt->execute([
                'appointment_id' => $appointment['id'],
                'patient_id' => $appointment['patient_id'],
                'doctor_id' => $appointment['doctor_id'],
                'motive' => $appointment['reason'] ?: 'Consultation',
            ]);

            return (int) $pdo->lastInsertId();
        });
    }

    public function consultationForm(int $userId, int $consultationId): array
    {
        $consultation = $this->consultationForDoctor($userId, $consultationId, true);
        if (!empty($consultation['has_other_active'])) {
            $doctor = $this->requireDoctor($userId);
            $stmt = Database::pdo()->prepare('SELECT id, record_number, first_name, last_name
                FROM patients WHERE id = :patient_id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['patient_id' => (int) $consultation['patient_id']]);
            $patient = $stmt->fetch();
            if (!is_array($patient)) {
                throw new \InvalidArgumentException('Patient inaccessible.');
            }

            return [
                'doctor' => $doctor,
                'patient' => $patient,
                'consultation' => $consultation,
                'allergies' => [],
                'history' => [],
                'vitals' => [],
                'consultations' => [],
                'documents' => [],
                'examResults' => [],
                'prescriptionItems' => [],
                'medicines' => [],
                'examRequests' => [],
            ];
        }
        $consultation['anamnesis'] = decrypt_sensitive($consultation['anamnesis_encrypted'] ?? null);
        $consultation['clinical_exam'] = decrypt_sensitive($consultation['clinical_exam_encrypted'] ?? null);
        $consultation['diagnosis'] = decrypt_sensitive($consultation['diagnosis_encrypted'] ?? null);
        $consultation['care_plan'] = decrypt_sensitive($consultation['care_plan_encrypted'] ?? null);

        return array_merge($this->medicalRecord($userId, (int) $consultation['patient_id']), [
            'consultation' => $consultation,
            'prescriptionItems' => $this->prescriptionItems($consultationId),
            'medicines' => (new MedicineService())->activeMedicines(),
            'examRequests' => $this->examRequests($consultationId),
        ]);
    }

    public function closeExpiredConsultations(?int $doctorId = null): int
    {
        return Database::transaction(function (PDO $pdo) use ($doctorId): int {
            $doctorFilter = $doctorId === null ? '' : ' AND c.doctor_id = :doctor_id';
            $params = $doctorId === null ? [] : ['doctor_id' => $doctorId];
            $stmt = $pdo->prepare('UPDATE consultations c
                INNER JOIN appointments a ON a.id = c.appointment_id
                SET c.locked_at = DATE_ADD(a.start_datetime, INTERVAL 30 MINUTE), c.updated_at = NOW()
                WHERE c.locked_at IS NULL AND c.deleted_at IS NULL
                  AND a.status = "en_consultation" AND a.deleted_at IS NULL
                  AND a.start_datetime <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)' . $doctorFilter);
            $stmt->execute($params);
            $closedCount = $stmt->rowCount();

            $appointmentDoctorFilter = $doctorId === null ? '' : ' AND a.doctor_id = :doctor_id';
            $stmt = $pdo->prepare('UPDATE appointments a
                SET a.status = "termine", a.updated_at = NOW()
                WHERE a.status = "en_consultation" AND a.deleted_at IS NULL
                  AND a.start_datetime <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                  AND EXISTS (
                    SELECT 1 FROM consultations c
                    WHERE c.appointment_id = a.id AND c.doctor_id = a.doctor_id
                      AND c.locked_at = DATE_ADD(a.start_datetime, INTERVAL 30 MINUTE)
                      AND c.deleted_at IS NULL
                  )' . $appointmentDoctorFilter);
            $stmt->execute($params);

            return $closedCount;
        });
    }

    public function saveConsultation(int $userId, int $consultationId, array $input): void
    {
        $consultation = $this->consultationForDoctor($userId, $consultationId);
        if (!empty($consultation['locked_at'])) {
            $content = trim((string) ($input['addendum'] ?? ''));
            if ($content === '') {
                throw new \InvalidArgumentException('Une consultation cloturee necessite un addendum.');
            }

            $stmt = Database::pdo()->prepare('INSERT INTO medical_records (consultation_id, author_id, type, content_encrypted, created_at)
                VALUES (:consultation_id, :author_id, "addendum", :content, NOW())');
            $stmt->execute([
                'consultation_id' => $consultationId,
                'author_id' => $userId,
                'content' => encrypt_sensitive($content),
            ]);
            return;
        }

        $stmt = Database::pdo()->prepare('UPDATE consultations
            SET motive = :motive,
                anamnesis_encrypted = :anamnesis,
                clinical_exam_encrypted = :clinical_exam,
                diagnosis_encrypted = :diagnosis,
                icd10_code = :icd10_code,
                care_plan_encrypted = :care_plan,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            'motive' => trim((string) ($input['motive'] ?? 'Consultation')),
            'anamnesis' => encrypt_sensitive((string) ($input['anamnesis'] ?? '')),
            'clinical_exam' => encrypt_sensitive((string) ($input['clinical_exam'] ?? '')),
            'diagnosis' => encrypt_sensitive((string) ($input['diagnosis'] ?? '')),
            'icd10_code' => trim((string) ($input['icd10_code'] ?? '')) ?: null,
            'care_plan' => encrypt_sensitive((string) ($input['care_plan'] ?? '')),
            'id' => $consultationId,
        ]);
    }

    public function closeConsultation(int $userId, int $consultationId): void
    {
        $consultation = $this->consultationForDoctor($userId, $consultationId, true);
        Database::transaction(function (PDO $pdo) use ($consultation, $consultationId): void {
            $stmt = $pdo->prepare('UPDATE consultations SET locked_at = COALESCE(locked_at, NOW()), updated_at = NOW() WHERE id = :id');
            $stmt->execute(['id' => $consultationId]);

            if (!empty($consultation['appointment_id'])) {
                $stmt = $pdo->prepare('UPDATE appointments SET status = "termine", updated_at = NOW() WHERE id = :id');
                $stmt->execute(['id' => $consultation['appointment_id']]);
            }
        });
    }

    public function addExamRequest(int $userId, int $consultationId, array $input): void
    {
        $consultation = $this->consultationForDoctor($userId, $consultationId);
        if (!empty($consultation['locked_at'])) {
            throw new \InvalidArgumentException('Un examen ne peut pas être ajouté après la clôture de la consultation.');
        }
        $label = trim((string) ($input['label'] ?? ''));
        $type = (string) ($input['type'] ?? 'laboratoire');
        if ($label === '' || !in_array($type, ['laboratoire', 'imagerie', 'autre'], true)) {
            throw new \InvalidArgumentException('Examen invalide.');
        }

        $stmt = Database::pdo()->prepare('INSERT INTO exam_requests (consultation_id, type, label, status, requested_at)
            VALUES (:consultation_id, :type, :label, "demande", NOW())');
        $stmt->execute(['consultation_id' => $consultationId, 'type' => $type, 'label' => $label]);
    }

    public function addPrescription(int $userId, int $consultationId, array $input): void
    {
        $consultation = $this->consultationForDoctor($userId, $consultationId);
        $medicineId = filter_var($input['medicine_id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $dosage = trim((string) ($input['dosage'] ?? ''));
        $frequency = trim((string) ($input['frequency'] ?? ''));
        $duration = trim((string) ($input['duration'] ?? ''));
        $instructions = trim((string) ($input['instructions'] ?? ''));
        if ($medicineId === false || $medicineId < 1 || $quantity === false || $quantity < 1 || $quantity > 1000000
            || $dosage === '' || mb_strlen($dosage) > 100
            || $frequency === '' || mb_strlen($frequency) > 150
            || $duration === '' || mb_strlen($duration) > 100
            || mb_strlen($instructions) > 2000) {
            throw new \InvalidArgumentException('Médicament, quantité, posologie et durée sont obligatoires. Vérifiez aussi les longueurs des champs.');
        }

        if (!empty($consultation['locked_at'])) {
            throw new \InvalidArgumentException('Une ordonnance ne peut pas être modifiée après la clôture de la consultation.');
        }

        Database::transaction(function (PDO $pdo) use (
            $userId,
            $consultationId,
            $consultation,
            $medicineId,
            $quantity,
            $dosage,
            $frequency,
            $duration,
            $instructions
        ): void {
            $lockedConsultation = $pdo->prepare('SELECT locked_at FROM consultations
                WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
            $lockedConsultation->execute(['id' => $consultationId]);
            $lockedAt = $lockedConsultation->fetchColumn();
            if ($lockedAt === false || $lockedAt !== null) {
                throw new \InvalidArgumentException('Une ordonnance ne peut pas être ajoutée à cette consultation.');
            }

            $medicineStmt = $pdo->prepare('SELECT id, name, dosage_form, strength, stock_unit, stock_quantity
                FROM medicines WHERE id = :id AND is_active = 1 FOR UPDATE');
            $medicineStmt->execute(['id' => $medicineId]);
            $medicine = $medicineStmt->fetch();
            if (!is_array($medicine)) {
                throw new \InvalidArgumentException('Ce médicament n’est plus disponible dans le catalogue.');
            }
            foreach ($this->allergies((int) $consultation['patient_id']) as $allergy) {
                if (stripos((string) $medicine['name'], (string) $allergy['allergen']) !== false) {
                    throw new \InvalidArgumentException('Alerte allergie : ce médicament semble contenir ' . $allergy['allergen'] . '.');
                }
            }

            $stockBefore = (int) $medicine['stock_quantity'];
            if ($quantity > $stockBefore) {
                throw new \InvalidArgumentException('Stock insuffisant pour prescrire cette quantité. Stock disponible : '
                    . $stockBefore . ' ' . $medicine['stock_unit'] . '.');
            }

            $stmt = $pdo->prepare('SELECT id FROM prescriptions WHERE consultation_id = :consultation_id LIMIT 1');
            $stmt->execute(['consultation_id' => $consultationId]);
            $prescriptionId = $stmt->fetchColumn();

            if ($prescriptionId === false) {
                $number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $stmt = $pdo->prepare('INSERT INTO prescriptions (consultation_id, prescription_number, qr_token_hash, created_at)
                    VALUES (:consultation_id, :number, :qr, NOW())');
                $stmt->execute([
                    'consultation_id' => $consultationId,
                    'number' => $number,
                    'qr' => hash_token($number),
                ]);
                $prescriptionId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare('INSERT INTO prescription_items
                (prescription_id, medicine_id, medicine_form, medicine_strength, medicine_name, dosage, frequency, duration,
                 quantity, quantity_unit, instructions)
                VALUES (:prescription_id, :medicine_id, :medicine_form, :medicine_strength, :medicine_name, :dosage,
                    :frequency, :duration, :quantity, :quantity_unit, :instructions)');
            $stmt->execute([
                'prescription_id' => $prescriptionId,
                'medicine_id' => $medicineId,
                'medicine_form' => $medicine['dosage_form'],
                'medicine_strength' => $medicine['strength'],
                'medicine_name' => $medicine['name'],
                'dosage' => $dosage,
                'frequency' => $frequency,
                'duration' => $duration,
                'quantity' => $quantity,
                'quantity_unit' => $medicine['stock_unit'],
                'instructions' => $instructions !== '' ? $instructions : null,
            ]);
            $prescriptionItemId = (int) $pdo->lastInsertId();
            $stockAfter = $stockBefore - $quantity;

            $updateStock = $pdo->prepare('UPDATE medicines
                SET stock_quantity = :stock_quantity, updated_at = NOW()
                WHERE id = :id AND stock_quantity = :stock_before');
            $updateStock->execute([
                'stock_quantity' => $stockAfter,
                'id' => $medicineId,
                'stock_before' => $stockBefore,
            ]);
            if ($updateStock->rowCount() !== 1) {
                throw new \RuntimeException('Le stock a changé pendant la prescription. Veuillez réessayer.');
            }

            $movement = $pdo->prepare('INSERT INTO medicine_stock_movements
                (medicine_id, movement_type, quantity, quantity_unit, stock_before, stock_after,
                 prescription_item_id, reason, created_by, created_at)
                VALUES (:medicine_id, "ordonnance", :quantity, :quantity_unit, :stock_before,
                    :stock_after, :prescription_item_id, :reason, :created_by, NOW())');
            $movement->execute([
                'medicine_id' => $medicineId,
                'quantity' => $quantity,
                'quantity_unit' => $medicine['stock_unit'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'prescription_item_id' => $prescriptionItemId,
                'reason' => 'Prescription ' . $medicine['name'],
                'created_by' => $userId,
            ]);
            $this->audit->log('medicine_prescribed', 'medicine', $medicineId, $userId, [
                'prescription_item_id' => $prescriptionItemId,
                'movement_type' => 'ordonnance',
                'quantity' => $quantity,
                'unit' => $medicine['stock_unit'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
            ]);
        });
    }

    public function cancelPrescriptionItem(int $userId, int $consultationId, int $prescriptionItemId): void
    {
        $consultation = $this->consultationForDoctor($userId, $consultationId);
        if (!empty($consultation['locked_at'])) {
            throw new \InvalidArgumentException('Une ordonnance ne peut pas être annulée après la clôture de la consultation.');
        }

        Database::transaction(function (PDO $pdo) use ($userId, $consultationId, $prescriptionItemId): void {
            $lockedConsultation = $pdo->prepare('SELECT locked_at FROM consultations
                WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
            $lockedConsultation->execute(['id' => $consultationId]);
            $lockedAt = $lockedConsultation->fetchColumn();
            if ($lockedAt === false || $lockedAt !== null) {
                throw new \InvalidArgumentException('Une ordonnance ne peut pas être annulée après la clôture de la consultation.');
            }

            $itemStmt = $pdo->prepare('SELECT pi.id, pi.medicine_id, pi.medicine_name, pi.quantity, pi.quantity_unit
                FROM prescription_items pi
                INNER JOIN prescriptions p ON p.id = pi.prescription_id
                WHERE pi.id = :item_id AND p.consultation_id = :consultation_id AND pi.status = "active"
                FOR UPDATE');
            $itemStmt->execute(['item_id' => $prescriptionItemId, 'consultation_id' => $consultationId]);
            $item = $itemStmt->fetch();
            if (!is_array($item) || $item['medicine_id'] === null || $item['quantity'] === null) {
                throw new \InvalidArgumentException('Cette ligne d’ordonnance est introuvable ou ne peut pas être annulée.');
            }

            $movementCheck = $pdo->prepare('SELECT COUNT(*) FROM medicine_stock_movements
                WHERE prescription_item_id = :item_id AND movement_type = "ordonnance"');
            $movementCheck->execute(['item_id' => $prescriptionItemId]);
            if ((int) $movementCheck->fetchColumn() !== 1) {
                throw new \RuntimeException('Le mouvement de stock initial de cette ordonnance est introuvable.');
            }

            $medicineStmt = $pdo->prepare('SELECT stock_quantity FROM medicines WHERE id = :id FOR UPDATE');
            $medicineStmt->execute(['id' => (int) $item['medicine_id']]);
            $stockBefore = $medicineStmt->fetchColumn();
            if ($stockBefore === false) {
                throw new \RuntimeException('Le médicament associé à cette ordonnance est introuvable.');
            }
            $stockBefore = (int) $stockBefore;
            $quantity = (int) $item['quantity'];
            if ($stockBefore > 4294967295 - $quantity) {
                throw new \RuntimeException('Le retour de stock dépasserait la capacité maximale autorisée.');
            }
            $stockAfter = $stockBefore + $quantity;

            $updateStock = $pdo->prepare('UPDATE medicines SET stock_quantity = :stock_quantity, updated_at = NOW() WHERE id = :id');
            $updateStock->execute(['stock_quantity' => $stockAfter, 'id' => (int) $item['medicine_id']]);
            $returnMovement = $pdo->prepare('INSERT INTO medicine_stock_movements
                (medicine_id, movement_type, quantity, quantity_unit, stock_before, stock_after,
                 prescription_item_id, reason, created_by, created_at)
                VALUES (:medicine_id, "retour", :quantity, :quantity_unit, :stock_before, :stock_after,
                    :prescription_item_id, :reason, :created_by, NOW())');
            $returnMovement->execute([
                'medicine_id' => (int) $item['medicine_id'],
                'quantity' => $quantity,
                'quantity_unit' => $item['quantity_unit'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'prescription_item_id' => $prescriptionItemId,
                'reason' => 'Annulation de la ligne d’ordonnance',
                'created_by' => $userId,
            ]);
            $deactivate = $pdo->prepare('UPDATE prescription_items SET status = "annulee" WHERE id = :id AND status = "active"');
            $deactivate->execute(['id' => $prescriptionItemId]);
            if ($deactivate->rowCount() !== 1) {
                throw new \RuntimeException('La ligne d’ordonnance a changé pendant son annulation.');
            }

            $this->audit->log('medicine_prescription_cancelled', 'medicine', (int) $item['medicine_id'], $userId, [
                'movement_type' => 'retour',
                'quantity' => $quantity,
                'unit' => $item['quantity_unit'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => 'Annulation de la ligne d’ordonnance : ' . $item['medicine_name'],
            ]);
        });
    }

    public function schedules(int $userId): array
    {
        $doctor = $this->requireDoctor($userId);
        $stmt = Database::pdo()->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = :doctor_id ORDER BY weekday, start_time');
        $stmt->execute(['doctor_id' => $doctor['id']]);

        return ['doctor' => $doctor, 'schedules' => $stmt->fetchAll()];
    }

    public function addSchedule(int $userId, array $input): void
    {
        $doctor = $this->requireDoctor($userId);
        $weekday = (int) ($input['weekday'] ?? 0);
        $start = (string) ($input['start_time'] ?? '');
        $end = (string) ($input['end_time'] ?? '');
        if ($weekday < 1 || $weekday > 7 || $start === '' || $end === '' || $start >= $end) {
            throw new \InvalidArgumentException('Horaire invalide.');
        }

        $stmt = Database::pdo()->prepare('INSERT IGNORE INTO doctor_schedules (doctor_id, weekday, start_time, end_time, created_at)
            VALUES (:doctor_id, :weekday, :start_time, :end_time, NOW())');
        $stmt->execute(['doctor_id' => $doctor['id'], 'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end]);
    }

    private function requireDoctor(int $userId): array
    {
        $doctor = $this->doctorForUser($userId);
        if ($doctor === null) {
            throw new \RuntimeException('Aucun profil medecin associe.');
        }

        return $doctor;
    }

    private function appointmentForDoctor(int $doctorId, int $appointmentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM appointments WHERE id = :id AND doctor_id = :doctor_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $appointmentId, 'doctor_id' => $doctorId]);
        $appointment = $stmt->fetch();

        return is_array($appointment) ? $appointment : null;
    }

    private function consultationForDoctor(int $userId, int $consultationId, bool $allowConcurrentReadOnly = false): array
    {
        $doctor = $this->requireDoctor($userId);
        $this->closeExpiredConsultations((int) $doctor['id']);
        $stmt = Database::pdo()->prepare('SELECT c.*, DATE_ADD(a.start_datetime, INTERVAL 30 MINUTE) AS scheduled_closure_at
            FROM consultations c
            LEFT JOIN appointments a ON a.id = c.appointment_id
            WHERE c.id = :id AND c.doctor_id = :doctor_id AND c.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $consultationId, 'doctor_id' => $doctor['id']]);
        $consultation = $stmt->fetch();
        if (!is_array($consultation)) {
            throw new \InvalidArgumentException('Consultation inaccessible.');
        }

        if (empty($consultation['locked_at'])) {
            $stmt = Database::pdo()->prepare('SELECT id FROM consultations
                WHERE doctor_id = :doctor_id AND patient_id <> :patient_id
                  AND locked_at IS NULL AND deleted_at IS NULL
                LIMIT 1');
            $stmt->execute([
                'doctor_id' => (int) $doctor['id'],
                'patient_id' => (int) $consultation['patient_id'],
            ]);
            if ($stmt->fetchColumn() !== false) {
                if (!$allowConcurrentReadOnly) {
                    throw new \InvalidArgumentException("Vous ne pouvez pas consulter un autre patient tant qu'une consultation est en cours.");
                }
                $consultation['has_other_active'] = true;
            }
        }

        return $consultation;
    }

    private function canAccessPatient(int $doctorId, int $patientId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = :doctor_id AND patient_id = :patient_id AND deleted_at IS NULL');
        $stmt->execute(['doctor_id' => $doctorId, 'patient_id' => $patientId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function queueForDoctor(int $doctorId): array
    {
        $stmt = Database::pdo()->prepare('SELECT q.*, p.record_number, p.first_name, p.last_name
            FROM queue_triage q
            INNER JOIN patients p ON p.id = q.patient_id
            INNER JOIN appointments a ON a.id = q.appointment_id
            WHERE a.doctor_id = :doctor_id AND q.completed_at IS NULL
            ORDER BY FIELD(q.priority, "urgence_vitale", "urgent", "normal", "non_urgent"), q.arrived_at ASC');
        $stmt->execute(['doctor_id' => $doctorId]);

        return $stmt->fetchAll();
    }

    private function activeConsultations(int $doctorId): array
    {
        $stmt = Database::pdo()->prepare('SELECT c.id, c.appointment_id, c.patient_id, c.created_at,
                p.record_number, p.first_name, p.last_name
            FROM consultations c
            INNER JOIN patients p ON p.id = c.patient_id
            WHERE c.doctor_id = :doctor_id AND c.locked_at IS NULL AND c.deleted_at IS NULL
            ORDER BY c.created_at DESC, c.id DESC');
        $stmt->execute(['doctor_id' => $doctorId]);

        return $stmt->fetchAll();
    }

    private function stats(int $doctorId): array
    {
        $stmt = Database::pdo()->prepare('SELECT
            SUM(DATE(start_datetime) = CURDATE() AND status NOT IN ("annule", "refuse", "expire")) AS today_total,
            SUM(DATE(start_datetime) = CURDATE() AND status = "termine") AS completed_total,
            SUM(DATE(start_datetime) = CURDATE() AND status = "absent") AS missed_total,
            SUM(start_datetime >= NOW() AND status IN ("en_attente_validation", "reporte", "confirme", "arrive")) AS upcoming_total
            FROM appointments WHERE doctor_id = :doctor_id AND deleted_at IS NULL');
        $stmt->execute(['doctor_id' => $doctorId]);

        $stats = $stmt->fetch() ?: [];
        return [
            'today_total' => (int) ($stats['today_total'] ?? 0),
            'completed_total' => (int) ($stats['completed_total'] ?? 0),
            'missed_total' => (int) ($stats['missed_total'] ?? 0),
            'upcoming_total' => (int) ($stats['upcoming_total'] ?? 0),
        ];
    }

    private function consultationTrend(int $doctorId): array
    {
        $stmt = Database::pdo()->prepare('SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM consultations
            WHERE doctor_id = :doctor_id AND deleted_at IS NULL
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at) ORDER BY day');
        $stmt->execute(['doctor_id' => $doctorId]);
        $rows = $stmt->fetchAll();
        $valuesByDay = [];
        foreach ($rows as $row) {
            $valuesByDay[(string) $row['day']] = (int) $row['total'];
        }

        $labels = [];
        $values = [];
        $today = new \DateTimeImmutable('today');
        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $today->modify('-' . $offset . ' days');
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
            $values[] = $valuesByDay[$key] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function allergies(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM patient_allergies WHERE patient_id = :patient_id AND deleted_at IS NULL ORDER BY severity DESC, allergen');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function history(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM patient_history WHERE patient_id = :patient_id AND deleted_at IS NULL ORDER BY created_at DESC');
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['notes'] = decrypt_sensitive($row['notes_encrypted'] ?? null) ?? $row['notes_encrypted'];
        }

        return $rows;
    }

    private function vitals(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM vitals WHERE patient_id = :patient_id ORDER BY recorded_at DESC LIMIT 10');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function consultations(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT c.*, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
            FROM consultations c
            INNER JOIN doctors d ON d.id = c.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            WHERE c.patient_id = :patient_id AND c.deleted_at IS NULL
            ORDER BY c.created_at DESC LIMIT 15');
        $stmt->execute(['patient_id' => $patientId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['diagnosis'] = decrypt_sensitive($row['diagnosis_encrypted'] ?? null);
            $row['anamnesis'] = decrypt_sensitive($row['anamnesis_encrypted'] ?? null);
            $row['clinical_exam'] = decrypt_sensitive($row['clinical_exam_encrypted'] ?? null);
            $row['care_plan'] = decrypt_sensitive($row['care_plan_encrypted'] ?? null);
        }

        return $rows;
    }

    private function documents(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT original_name, category, created_at FROM documents WHERE patient_id = :patient_id AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function examResults(int $patientId): array
    {
        $stmt = Database::pdo()->prepare('SELECT er.created_at, req.type, req.label
            FROM exam_results er
            INNER JOIN exam_requests req ON req.id = er.exam_request_id
            INNER JOIN consultations c ON c.id = req.consultation_id
            WHERE c.patient_id = :patient_id
            ORDER BY er.created_at DESC LIMIT 10');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }

    private function prescriptionItems(int $consultationId): array
    {
        $stmt = Database::pdo()->prepare('SELECT pi.*, p.prescription_number,
                pi.medicine_form AS dosage_form, pi.medicine_strength AS strength
            FROM prescription_items pi
            INNER JOIN prescriptions p ON p.id = pi.prescription_id
            WHERE p.consultation_id = :consultation_id
            ORDER BY pi.id DESC');
        $stmt->execute(['consultation_id' => $consultationId]);

        return $stmt->fetchAll();
    }

    private function examRequests(int $consultationId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM exam_requests WHERE consultation_id = :consultation_id ORDER BY requested_at DESC');
        $stmt->execute(['consultation_id' => $consultationId]);

        return $stmt->fetchAll();
    }
}
