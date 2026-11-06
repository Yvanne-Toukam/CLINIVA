<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;

final class AdminService
{
    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $pdo = Database::pdo();

        $stats = [
            'patients' => (int) $pdo->query('SELECT COUNT(*) FROM patients WHERE deleted_at IS NULL')->fetchColumn(),
            'doctors' => (int) $pdo->query('SELECT COUNT(*) FROM doctors WHERE deleted_at IS NULL')->fetchColumn(),
            'consultations_today' => (int) $pdo->query('SELECT COUNT(*) FROM consultations WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
            'appointments_today' => (int) $pdo->query('SELECT COUNT(*) FROM appointments WHERE DATE(start_datetime) = CURDATE() AND deleted_at IS NULL')->fetchColumn(),
            'revenue_month' => (int) $pdo->query('SELECT COALESCE(SUM(total_xaf), 0) FROM invoices WHERE status = "payee" AND MONTH(issued_at) = MONTH(CURDATE()) AND YEAR(issued_at) = YEAR(CURDATE())')->fetchColumn(),
            'pending_payments' => (int) $pdo->query('SELECT COUNT(*) FROM invoices WHERE status IN ("brouillon", "emise")')->fetchColumn(),
        ];

        $trend = $pdo->query('SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM consultations
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
            ORDER BY day ASC')->fetchAll();

        $labels = [];
        $values = [];
        $cursor = new \DateTimeImmutable('today');
        for ($i = 6; $i >= 0; $i--) {
            $date = $cursor->modify('-' . $i . ' day');
            $labels[] = $date->format('d/m');
            $values[] = 0;
        }

        foreach ($trend as $row) {
            $date = new \DateTimeImmutable((string) $row['day']);
            $label = $date->format('d/m');
            $offset = array_search($label, $labels, true);
            if ($offset !== false) {
                $values[$offset] = (int) $row['total'];
            }
        }

        $recentLogs = $pdo->query('SELECT al.*, actor.first_name, actor.last_name,
                target_user.first_name AS target_user_first_name, target_user.last_name AS target_user_last_name, target_user.role AS target_user_role,
                target_patient.first_name AS target_patient_first_name, target_patient.last_name AS target_patient_last_name,
                appointment_patient.first_name AS appointment_patient_first_name, appointment_patient.last_name AS appointment_patient_last_name,
                appointment_doctor_user.first_name AS appointment_doctor_first_name, appointment_doctor_user.last_name AS appointment_doctor_last_name,
                target_appointment.start_datetime AS appointment_start_datetime,
                doctor_user.first_name AS target_doctor_first_name, doctor_user.last_name AS target_doctor_last_name,
                target_specialty.name AS target_specialty_name,
                target_service.name AS target_service_name, target_service.code AS target_service_code
            FROM audit_logs al
            LEFT JOIN users actor ON actor.id = al.user_id
            LEFT JOIN users target_user ON al.entity_type = "user" AND target_user.id = al.entity_id
            LEFT JOIN patients target_patient ON al.entity_type = "patient" AND target_patient.id = al.entity_id
            LEFT JOIN appointments target_appointment ON al.entity_type = "appointment" AND target_appointment.id = al.entity_id
            LEFT JOIN patients appointment_patient ON appointment_patient.id = target_appointment.patient_id
            LEFT JOIN doctors appointment_doctor ON appointment_doctor.id = target_appointment.doctor_id
            LEFT JOIN users appointment_doctor_user ON appointment_doctor_user.id = appointment_doctor.user_id
            LEFT JOIN doctors target_doctor ON al.entity_type = "doctor" AND target_doctor.id = al.entity_id
            LEFT JOIN users doctor_user ON doctor_user.id = target_doctor.user_id
            LEFT JOIN specialties target_specialty ON al.entity_type = "specialty" AND target_specialty.id = al.entity_id
            LEFT JOIN services target_service ON al.entity_type = "service" AND target_service.id = al.entity_id
            ORDER BY al.created_at DESC
            LIMIT 10')->fetchAll();
        $recentLogs = $this->presentAuditLogs($recentLogs);

        return [
            'stats' => $stats,
            'chart' => [
                'labels' => $labels,
                'values' => $values,
            ],
            'analytics' => $this->analytics($pdo),
            'auditLogs' => $recentLogs,
        ];
    }

    private function presentAuditLogs(array $logs): array
    {
        $actionLabels = [
            'admin_staff_user_created' => 'Création d’un compte du personnel',
            'admin_staff_user_updated' => 'Modification d’un compte du personnel',
            'admin_password_reset_requested' => 'Demande de réinitialisation du mot de passe',
            'admin_user_status_changed' => 'Activation ou désactivation d’un compte',
            'admin_specialty_created' => 'Création d’une spécialité',
            'admin_specialty_updated' => 'Modification d’une spécialité',
            'admin_specialty_status_changed' => 'Activation ou désactivation d’une spécialité',
            'admin_service_created' => 'Création d’un tarif ou d’un acte',
            'admin_service_updated' => 'Modification d’un tarif ou d’un acte',
            'admin_service_status_changed' => 'Activation ou désactivation d’un tarif',
            'admin_contact_message_updated' => 'Mise à jour d’un message de contact',
            'admin_public_settings_updated' => 'Modification des paramètres de la clinique',
            'medicine_created' => 'Ajout d’un médicament au catalogue',
            'medicine_updated' => 'Modification d’un médicament',
            'medicine_status_changed' => 'Activation ou désactivation d’un médicament',
            'medicine_stock_moved' => 'Mouvement manuel de stock',
            'medicine_prescribed' => 'Stock déduit lors d’une prescription',
            'medicine_prescription_cancelled' => 'Annulation d’une prescription et retour au stock',
            'reception_appointment_created' => 'Création d’un rendez-vous',
            'reception_appointment_cancelled' => 'Annulation d’un rendez-vous par la réception',
            'appointment_validated_by_secretary' => 'Validation d’un rendez-vous',
            'appointment_refused_by_secretary' => 'Refus d’un rendez-vous',
            'appointment_rescheduled_by_secretary' => 'Proposition d’un nouveau créneau',
            'appointment_message_sent' => 'Envoi d’un message concernant un rendez-vous',
            'patient_appointment_response' => 'Réponse du patient à une proposition de rendez-vous',
            'patient_appointment_cancelled' => 'Annulation d’un rendez-vous par le patient',
            'patient_appointment_rescheduled' => 'Demande de modification d’un rendez-vous',
            'reception_patient_created' => 'Création d’un dossier patient',
            'reception_triage_recorded' => 'Enregistrement du triage d’un patient',
            'reception_doctor_schedule_added' => 'Ajout d’une disponibilité médecin',
            'reception_doctor_time_off_added' => 'Ajout d’une absence médecin',
            'reception_invoice_created' => 'Création d’une facture',
            'reception_doctor_alert_resolved' => 'Traitement d’un signalement médecin',
            'doctor_impediment_reported' => 'Signalement d’un empêchement médecin',
            'medical_record_view' => 'Consultation d’un dossier médical',
            'patient_medical_record_view' => 'Consultation d’un dossier médical',
            'patient_medical_documents_view' => 'Consultation des documents médicaux',
            'patient_medical_history_view' => 'Consultation de l’historique médical',
            'medical_record_docx_export' => 'Export d’un dossier médical',
            'patient_document_export' => 'Export d’un document patient',
            'seed_import' => 'Importation des données de démonstration',
        ];
        $entityLabels = [
            'user' => 'Compte',
            'specialty' => 'Spécialité',
            'service' => 'Tarif / acte',
            'medicine' => 'Médicament',
            'settings' => 'Paramètres de la clinique',
            'contact_message' => 'Message de contact',
            'appointment' => 'Rendez-vous',
            'patient' => 'Patient',
            'doctor' => 'Médecin',
            'invoice' => 'Facture',
            'doctor_impediment_alert' => 'Signalement médecin',
            'document' => 'Document',
            'system' => 'Système',
        ];
        $noChangeActions = [
            'admin_staff_user_updated',
            'admin_specialty_updated',
            'admin_service_updated',
            'admin_contact_message_updated',
            'admin_public_settings_updated',
        ];
        $legacyDetails = [
            'admin_password_reset_requested' => 'Un lien de réinitialisation a été demandé pour le compte concerné.',
            'reception_appointment_created' => 'Le rendez-vous a été créé. Les anciennes entrées ne conservent pas le motif saisi.',
            'reception_appointment_cancelled' => 'Le rendez-vous a été annulé par la réception.',
            'patient_appointment_cancelled' => 'Le rendez-vous a été annulé par le patient.',
        ];

        foreach ($logs as &$log) {
            $action = (string) ($log['action'] ?? '');
            $entityType = (string) ($log['entity_type'] ?? '');
            $metadata = json_decode((string) ($log['metadata'] ?? ''), true);
            $changes = is_array($metadata) && is_array($metadata['changes'] ?? null)
                ? array_values(array_filter($metadata['changes'], 'is_string'))
                : [];
            $medicineDetails = '';
            if ($entityType === 'medicine' && is_array($metadata)) {
                if ($action === 'medicine_created') {
                    $medicineDetails = 'Ajout au catalogue : ' . (string) ($metadata['name'] ?? 'Médicament')
                        . ' · ' . (string) ($metadata['dosage_form'] ?? '') . ' · unité : ' . (string) ($metadata['stock_unit'] ?? '');
                } elseif ($action === 'medicine_updated') {
                    $fields = is_array($metadata['changed_fields'] ?? null)
                        ? array_values(array_filter($metadata['changed_fields'], 'is_string'))
                        : [];
                    $medicineDetails = $fields !== [] ? 'Champs modifiés : ' . implode(', ', $fields) : 'Aucune modification détectée.';
                } elseif ($action === 'medicine_status_changed') {
                    $medicineDetails = !empty($metadata['is_active']) ? 'Médicament réactivé.' : 'Médicament désactivé.';
                } elseif (in_array($action, ['medicine_stock_moved', 'medicine_prescribed', 'medicine_prescription_cancelled'], true)) {
                    $movementType = (string) ($metadata['movement_type'] ?? '');
                    $movementLabel = [
                        'entree' => 'Entrée',
                        'sortie' => 'Sortie manuelle',
                        'ordonnance' => 'Déduction par ordonnance',
                        'retour' => 'Retour après annulation',
                    ][$movementType] ?? 'Mouvement';
                    $medicineDetails = $movementLabel . ' : ' . (int) ($metadata['quantity'] ?? 0)
                        . ' ' . (string) ($metadata['unit'] ?? '')
                        . ' · stock ' . (int) ($metadata['stock_before'] ?? 0)
                        . ' → ' . (int) ($metadata['stock_after'] ?? 0);
                    if (!empty($metadata['reason'])) {
                        $medicineDetails .= ' · Motif : ' . (string) $metadata['reason'];
                    }
                }
            }

            $log['action_label'] = $actionLabels[$action] ?? ucfirst(str_replace('_', ' ', $action));
            $entityLabel = $entityLabels[$entityType] ?? ucfirst(str_replace('_', ' ', $entityType));
            $entityId = $log['entity_id'] ?? null;
            $entityDetail = '';
            if ($entityType === 'user' && !empty($log['target_user_first_name'])) {
                $roleLabels = ['admin' => 'administrateur', 'medecin' => 'médecin', 'secretaire' => 'secrétaire', 'patient' => 'patient'];
                $targetName = trim((string) $log['target_user_first_name'] . ' ' . (string) $log['target_user_last_name']);
                $targetRole = $roleLabels[(string) ($log['target_user_role'] ?? '')] ?? '';
                $entityDetail = ' — ' . $targetName . ($targetRole !== '' ? ' (' . $targetRole . ')' : '');
            } elseif ($entityType === 'patient' && !empty($log['target_patient_first_name'])) {
                $entityDetail = ' — ' . trim((string) $log['target_patient_first_name'] . ' ' . (string) $log['target_patient_last_name']);
            } elseif ($entityType === 'appointment' && !empty($log['appointment_patient_first_name'])) {
                $patientName = trim((string) $log['appointment_patient_first_name'] . ' ' . (string) $log['appointment_patient_last_name']);
                $appointmentDate = !empty($log['appointment_start_datetime'])
                    ? date('d/m/Y à H:i', strtotime((string) $log['appointment_start_datetime']))
                    : '';
                $doctorName = trim((string) ($log['appointment_doctor_first_name'] ?? '') . ' ' . (string) ($log['appointment_doctor_last_name'] ?? ''));
                $entityDetail = ' — ' . $patientName . ($appointmentDate !== '' ? ' · ' . $appointmentDate : '')
                    . ($doctorName !== '' ? ' · Dr ' . $doctorName : '');
            } elseif ($entityType === 'doctor' && !empty($log['target_doctor_first_name'])) {
                $entityDetail = ' — Dr ' . trim((string) $log['target_doctor_first_name'] . ' ' . (string) $log['target_doctor_last_name']);
            } elseif ($entityType === 'specialty' && !empty($log['target_specialty_name'])) {
                $entityDetail = ' — ' . (string) $log['target_specialty_name'];
            } elseif ($entityType === 'service' && !empty($log['target_service_name'])) {
                $entityDetail = ' — ' . trim((string) ($log['target_service_code'] ?? '') . ' ' . (string) $log['target_service_name']);
            }
            $log['entity_label'] = ($entityId === null
                ? $entityLabel
                : $entityLabel . ' n°' . (string) $entityId) . $entityDetail;
            $log['details_label'] = $changes !== []
                ? implode(' · ', $changes)
                : ($medicineDetails !== ''
                    ? $medicineDetails
                    : (in_array($action, $noChangeActions, true)
                        ? 'Aucune modification détectée.'
                        : ($legacyDetails[$action] ?? 'Aucun détail complémentaire n’a été conservé pour cette action.')));
        }
        unset($log);

        return $logs;
    }

    private function analytics(\PDO $pdo): array
    {
        $start = (new \DateTimeImmutable('today'))->modify('-29 days');
        $startDate = $start->format('Y-m-d');
        $doctorQuery = $pdo->prepare('SELECT d.id, d.slot_duration_minutes, u.first_name, u.last_name, s.name AS specialty,
                (SELECT COUNT(*) FROM consultations c WHERE c.doctor_id = d.id AND c.deleted_at IS NULL AND c.created_at >= :consult_start) AS consultations_30d,
                (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.deleted_at IS NULL
                    AND a.start_datetime >= :appointment_start AND a.start_datetime <= NOW()
                    AND a.status IN ("en_attente_validation", "reporte", "confirme", "arrive", "en_consultation", "termine", "absent")) AS appointments_30d
            FROM doctors d
            INNER JOIN users u ON u.id = d.user_id
            INNER JOIN specialties s ON s.id = d.specialty_id
            WHERE d.deleted_at IS NULL AND u.deleted_at IS NULL AND u.is_active = 1');
        $doctorQuery->execute(['consult_start' => $startDate, 'appointment_start' => $startDate]);
        $doctors = $doctorQuery->fetchAll();

        $weekdayCounts = array_fill(1, 7, 0);
        for ($date = $start; $date <= new \DateTimeImmutable('today'); $date = $date->modify('+1 day')) {
            $weekdayCounts[(int) $date->format('N')]++;
        }
        $schedules = $pdo->query('SELECT ds.doctor_id, ds.weekday, ds.start_time, ds.end_time, d.slot_duration_minutes
            FROM doctor_schedules ds INNER JOIN doctors d ON d.id = ds.doctor_id
            WHERE d.deleted_at IS NULL')->fetchAll();
        $capacity = [];
        foreach ($schedules as $schedule) {
            $weekday = (int) $schedule['weekday'];
            $slotMinutes = max(1, (int) $schedule['slot_duration_minutes']);
            $minutes = max(0, (int) ((strtotime((string) $schedule['end_time']) - strtotime((string) $schedule['start_time'])) / 60));
            $capacity[(int) $schedule['doctor_id']] = ($capacity[(int) $schedule['doctor_id']] ?? 0)
                + intdiv($minutes, $slotMinutes) * ($weekdayCounts[$weekday] ?? 0);
        }

        foreach ($doctors as &$doctor) {
            $expected = $capacity[(int) $doctor['id']] ?? 0;
            $doctor['occupancy_percent'] = $expected > 0
                ? min(100, round(((int) $doctor['appointments_30d'] / $expected) * 100, 1))
                : null;
        }
        unset($doctor);

        $mostConsulted = $doctors;
        $leastConsulted = $doctors;
        $occupancy = $doctors;
        usort($mostConsulted, static fn(array $left, array $right): int => (int) $right['consultations_30d'] <=> (int) $left['consultations_30d']);
        usort($leastConsulted, static fn(array $left, array $right): int => (int) $left['consultations_30d'] <=> (int) $right['consultations_30d']);
        usort($occupancy, static function (array $left, array $right): int {
            if ($left['occupancy_percent'] === null) {
                return $right['occupancy_percent'] === null ? 0 : 1;
            }
            if ($right['occupancy_percent'] === null) {
                return -1;
            }
            return (float) $right['occupancy_percent'] <=> (float) $left['occupancy_percent'];
        });

        $motives = $pdo->prepare('SELECT motive, COUNT(*) AS total FROM consultations
            WHERE deleted_at IS NULL AND created_at >= :start GROUP BY motive ORDER BY total DESC LIMIT 5');
        $motives->execute(['start' => $startDate]);

        $specialties = $pdo->prepare('SELECT s.name, COUNT(c.id) AS total FROM consultations c
            INNER JOIN doctors d ON d.id = c.doctor_id INNER JOIN specialties s ON s.id = d.specialty_id
            WHERE c.deleted_at IS NULL AND c.created_at >= :start GROUP BY s.id, s.name ORDER BY total DESC');
        $specialties->execute(['start' => $startDate]);

        $statusQuery = $pdo->prepare('SELECT COUNT(*) AS total,
                SUM(status = "annule") AS cancelled,
                SUM(status = "absent") AS missed
            FROM appointments WHERE deleted_at IS NULL AND start_datetime >= :start AND start_datetime <= NOW()');
        $statusQuery->execute(['start' => $startDate]);
        $appointmentStatuses = $statusQuery->fetch() ?: ['total' => 0, 'cancelled' => 0, 'missed' => 0];

        $waitQuery = $pdo->prepare('SELECT AVG(TIMESTAMPDIFF(MINUTE, arrived_at, called_at))
            FROM queue_triage WHERE called_at IS NOT NULL AND arrived_at >= :start');
        $waitQuery->execute(['start' => $startDate]);
        $averageWait = (float) ($waitQuery->fetchColumn() ?: 0);

        $revenueQuery = $pdo->query('SELECT DATE_FORMAT(issued_at, "%Y-%m") AS period, SUM(total_xaf) AS total
            FROM invoices WHERE status = "payee" AND issued_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY DATE_FORMAT(issued_at, "%Y-%m") ORDER BY period');
        $revenueRows = $revenueQuery->fetchAll();
        $revenueByMonth = [];
        $month = new \DateTimeImmutable('first day of this month');
        for ($index = 5; $index >= 0; $index--) {
            $cursor = $month->modify('-' . $index . ' months');
            $revenueByMonth[$cursor->format('Y-m')] = 0;
        }
        foreach ($revenueRows as $row) {
            if (array_key_exists((string) $row['period'], $revenueByMonth)) {
                $revenueByMonth[(string) $row['period']] = (int) $row['total'];
            }
        }

        return [
            'period_start' => $startDate,
            'most_consulted' => array_slice($mostConsulted, 0, 5),
            'least_consulted' => array_slice($leastConsulted, 0, 5),
            'motives' => $motives->fetchAll(),
            'specialties' => $specialties->fetchAll(),
            'appointments' => [
                'total' => (int) ($appointmentStatuses['total'] ?? 0),
                'cancelled' => (int) ($appointmentStatuses['cancelled'] ?? 0),
                'missed' => (int) ($appointmentStatuses['missed'] ?? 0),
            ],
            'average_wait_minutes' => round($averageWait, 1),
            'occupancy' => array_slice($occupancy, 0, 10),
            'revenue' => [
                'labels' => array_map(static fn(string $period): string => date('m/Y', strtotime($period . '-01')), array_keys($revenueByMonth)),
                'values' => array_values($revenueByMonth),
            ],
        ];
    }

    public function managementData(array $filters = []): array
    {
        $pdo = Database::pdo();
        $auditWhere = [];
        $auditParams = [];
        $loginWhere = [];
        $loginParams = [];

        $auditUser = filter_var($filters['audit_user'] ?? null, FILTER_VALIDATE_INT);
        if ($auditUser !== false && $auditUser !== null && $auditUser > 0) {
            $auditWhere[] = 'al.user_id = :audit_user';
            $auditParams['audit_user'] = $auditUser;
        }
        $auditAction = trim((string) ($filters['audit_action'] ?? ''));
        if ($auditAction !== '') {
            $auditWhere[] = 'al.action LIKE :audit_action';
            $auditParams['audit_action'] = '%' . mb_substr($auditAction, 0, 100) . '%';
        }
        foreach (['audit_from' => '>=', 'audit_to' => '<='] as $field => $operator) {
            $date = (string) ($filters[$field] ?? '');
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) === 1) {
                $auditWhere[] = 'DATE(al.created_at) ' . $operator . ' :' . $field;
                $auditParams[$field] = $date;
            }
        }

        $loginEmail = trim((string) ($filters['login_email'] ?? ''));
        if ($loginEmail !== '') {
            $loginWhere[] = 'la.email LIKE :login_email';
            $loginParams['login_email'] = '%' . mb_substr($loginEmail, 0, 190) . '%';
        }
        $loginSuccess = (string) ($filters['login_success'] ?? '');
        if (in_array($loginSuccess, ['0', '1'], true)) {
            $loginWhere[] = 'la.success = :login_success';
            $loginParams['login_success'] = (int) $loginSuccess;
        }
        foreach (['login_from' => '>=', 'login_to' => '<='] as $field => $operator) {
            $date = (string) ($filters[$field] ?? '');
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) === 1) {
                $loginWhere[] = 'DATE(la.created_at) ' . $operator . ' :' . $field;
                $loginParams[$field] = $date;
            }
        }

        $auditSql = 'SELECT al.*, actor.first_name, actor.last_name,
                target_user.first_name AS target_user_first_name, target_user.last_name AS target_user_last_name, target_user.role AS target_user_role,
                target_patient.first_name AS target_patient_first_name, target_patient.last_name AS target_patient_last_name,
                appointment_patient.first_name AS appointment_patient_first_name, appointment_patient.last_name AS appointment_patient_last_name,
                appointment_doctor_user.first_name AS appointment_doctor_first_name, appointment_doctor_user.last_name AS appointment_doctor_last_name,
                target_appointment.start_datetime AS appointment_start_datetime,
                doctor_user.first_name AS target_doctor_first_name, doctor_user.last_name AS target_doctor_last_name,
                target_specialty.name AS target_specialty_name,
                target_service.name AS target_service_name, target_service.code AS target_service_code
            FROM audit_logs al
            LEFT JOIN users actor ON actor.id = al.user_id
            LEFT JOIN users target_user ON al.entity_type = "user" AND target_user.id = al.entity_id
            LEFT JOIN patients target_patient ON al.entity_type = "patient" AND target_patient.id = al.entity_id
            LEFT JOIN appointments target_appointment ON al.entity_type = "appointment" AND target_appointment.id = al.entity_id
            LEFT JOIN patients appointment_patient ON appointment_patient.id = target_appointment.patient_id
            LEFT JOIN doctors appointment_doctor ON appointment_doctor.id = target_appointment.doctor_id
            LEFT JOIN users appointment_doctor_user ON appointment_doctor_user.id = appointment_doctor.user_id
            LEFT JOIN doctors target_doctor ON al.entity_type = "doctor" AND target_doctor.id = al.entity_id
            LEFT JOIN users doctor_user ON doctor_user.id = target_doctor.user_id
            LEFT JOIN specialties target_specialty ON al.entity_type = "specialty" AND target_specialty.id = al.entity_id
            LEFT JOIN services target_service ON al.entity_type = "service" AND target_service.id = al.entity_id'
            . ($auditWhere !== [] ? ' WHERE ' . implode(' AND ', $auditWhere) : '')
            . ' ORDER BY al.created_at DESC LIMIT 100';
        $auditStmt = $pdo->prepare($auditSql);
        $auditStmt->execute($auditParams);

        $loginSql = 'SELECT la.id, la.email, la.user_id, la.ip_address, la.success, la.locked_until, la.created_at,
                u.first_name, u.last_name
            FROM login_attempts la LEFT JOIN users u ON u.id = la.user_id'
            . ($loginWhere !== [] ? ' WHERE ' . implode(' AND ', $loginWhere) : '')
            . ' ORDER BY la.created_at DESC LIMIT 100';
        $loginStmt = $pdo->prepare($loginSql);
        $loginStmt->execute($loginParams);

        return [
            'users' => $pdo->query('SELECT u.id, u.role, u.first_name, u.last_name, u.email, u.is_active, u.created_at,
                    d.specialty_id, d.license_number
                FROM users u LEFT JOIN doctors d ON d.user_id = u.id AND d.deleted_at IS NULL
                WHERE u.deleted_at IS NULL AND u.role IN ("admin", "medecin", "secretaire")
                ORDER BY u.created_at DESC LIMIT 100')->fetchAll(),
            'specialties' => $pdo->query('SELECT id, name, description, deleted_at FROM specialties ORDER BY name')->fetchAll(),
            'services' => $pdo->query('SELECT id, code, name, price_xaf, is_active FROM services WHERE deleted_at IS NULL ORDER BY name')->fetchAll(),
            'contactMessages' => $pdo->query('SELECT id, name, email, phone, message, status, created_at
                FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll(),
            'auditLogs' => $this->presentAuditLogs($auditStmt->fetchAll()),
            'loginAttempts' => $loginStmt->fetchAll(),
            'settings' => $pdo->query('SELECT setting_key, setting_value FROM settings
                WHERE setting_key IN ("clinic_email", "clinic_phone", "clinic_address", "clinic_opening_hours", "appointment_cancel_min_hours", "appointment_validation_hours")
                ORDER BY setting_key')->fetchAll(),
            'filters' => [
                'audit_user' => $auditUser > 0 ? (string) $auditUser : '',
                'audit_action' => $auditAction,
                'audit_from' => (string) ($filters['audit_from'] ?? ''),
                'audit_to' => (string) ($filters['audit_to'] ?? ''),
                'login_email' => $loginEmail,
                'login_success' => $loginSuccess,
                'login_from' => (string) ($filters['login_from'] ?? ''),
                'login_to' => (string) ($filters['login_to'] ?? ''),
            ],
        ];
    }

    public function createStaffUser(array $input, int $actorId): int
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $role = (string) ($input['role'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $errors = [];

        if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100 || mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
            $errors[] = 'Le nom et le prénom doivent contenir entre 2 et 100 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Adresse e-mail invalide.';
        }
        if (!in_array($role, ['admin', 'medecin', 'secretaire'], true)) {
            $errors[] = 'Rôle du compte invalide.';
        }
        $errors = array_merge($errors, (new PasswordPolicy())->errors($password, (string) ($input['password_confirmation'] ?? '')));

        $doctorData = null;
        if ($role === 'medecin') {
            $specialtyId = (int) ($input['specialty_id'] ?? 0);
            $licenseNumber = trim((string) ($input['license_number'] ?? ''));
            $specialty = Database::pdo()->prepare('SELECT id FROM specialties WHERE id = :id AND deleted_at IS NULL');
            $specialty->execute(['id' => $specialtyId]);
            if ($specialty->fetchColumn() === false || $licenseNumber === '' || mb_strlen($licenseNumber) > 100) {
                $errors[] = 'Un médecin doit avoir une spécialité active et un numéro de licence.';
            } else {
                $license = Database::pdo()->prepare('SELECT id FROM doctors WHERE license_number = :license LIMIT 1');
                $license->execute(['license' => $licenseNumber]);
                if ($license->fetchColumn() !== false) {
                    $errors[] = 'Ce numéro de licence est déjà attribué.';
                } else {
                    $doctorData = ['specialty_id' => $specialtyId, 'license_number' => $licenseNumber];
                }
            }
        }

        $existing = Database::pdo()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        if ($existing->fetchColumn() !== false) {
            $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        }
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $userId = Database::transaction(function (\PDO $pdo) use ($firstName, $lastName, $email, $role, $password, $doctorData): int {
            $stmt = $pdo->prepare('INSERT INTO users (role, first_name, last_name, email, password_hash, must_change_password, email_verified_at, is_active, created_at)
                VALUES (:role, :first_name, :last_name, :email, :password_hash, 1, NOW(), 1, NOW())');
            $stmt->execute([
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            ]);
            $id = (int) $pdo->lastInsertId();

            if ($doctorData !== null) {
                $doctor = $pdo->prepare('INSERT INTO doctors (user_id, specialty_id, license_number, created_at)
                    VALUES (:user_id, :specialty_id, :license_number, NOW())');
                $doctor->execute([
                    'user_id' => $id,
                    'specialty_id' => $doctorData['specialty_id'],
                    'license_number' => $doctorData['license_number'],
                ]);
            }

            return $id;
        });

        (new AuditLogger())->log('admin_staff_user_created', 'user', $userId, $actorId, ['changes' => ['Création de compte staff', 'Rôle : ' . $role]]);
        return $userId;
    }

    public function updateStaffUser(array $input, int $actorId): void
    {
        $userId = (int) ($input['user_id'] ?? 0);
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $role = (string) ($input['role'] ?? '');
        if ($userId <= 0 || mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100 || mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
            throw new \InvalidArgumentException('Compte, nom ou prénom invalide.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new \InvalidArgumentException('Adresse e-mail invalide.');
        }
        if (!in_array($role, ['admin', 'medecin', 'secretaire'], true)) {
            throw new \InvalidArgumentException('Rôle staff invalide.');
        }
        if ($userId === $actorId && $role !== 'admin') {
            throw new \InvalidArgumentException('Vous ne pouvez pas retirer votre propre rôle administrateur.');
        }

        $pdo = Database::pdo();
        $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $existing->execute(['email' => $email, 'id' => $userId]);
        if ($existing->fetchColumn() !== false) {
            throw new \InvalidArgumentException('Cette adresse e-mail est déjà utilisée.');
        }

        $doctorData = null;
        if ($role === 'medecin') {
            $specialtyId = (int) ($input['specialty_id'] ?? 0);
            $licenseNumber = trim((string) ($input['license_number'] ?? ''));
            $specialty = $pdo->prepare('SELECT id FROM specialties WHERE id = :id AND deleted_at IS NULL');
            $specialty->execute(['id' => $specialtyId]);
            if ($specialty->fetchColumn() === false || $licenseNumber === '' || mb_strlen($licenseNumber) > 100) {
                throw new \InvalidArgumentException('Le médecin doit avoir une spécialité active et un numéro de licence.');
            }
            $license = $pdo->prepare('SELECT id FROM doctors WHERE license_number = :license AND user_id <> :user_id LIMIT 1');
            $license->execute(['license' => $licenseNumber, 'user_id' => $userId]);
            if ($license->fetchColumn() !== false) {
                throw new \InvalidArgumentException('Ce numéro de licence est déjà attribué.');
            }
            $doctorData = ['specialty_id' => $specialtyId, 'license_number' => $licenseNumber];
        }

        $changedFields = Database::transaction(function (\PDO $pdo) use ($userId, $firstName, $lastName, $email, $role, $doctorData): array {
            $stmt = $pdo->prepare('SELECT role, first_name, last_name, email, is_active FROM users WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute(['id' => $userId]);
            $current = $stmt->fetch();
            if (!is_array($current) || $current['role'] === 'patient') {
                throw new \InvalidArgumentException('Compte staff introuvable.');
            }
            if ($current['role'] === 'admin' && $role !== 'admin' && (int) $current['is_active'] === 1) {
                $admins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin" AND is_active = 1 AND deleted_at IS NULL')->fetchColumn();
                if ($admins <= 1) {
                    throw new \InvalidArgumentException('Le dernier compte administrateur actif ne peut pas être rétrogradé.');
                }
            }

            $changedFields = [];
            $updatedValues = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'role' => $role,
            ];
            $fieldLabels = [
                'first_name' => 'Prénom',
                'last_name' => 'Nom',
                'email' => 'Adresse e-mail',
                'role' => 'Rôle',
            ];
            foreach ($updatedValues as $field => $value) {
                if ((string) $current[$field] !== $value) {
                    $changedFields[] = $fieldLabels[$field];
                }
            }

            $update = $pdo->prepare('UPDATE users SET role = :role, first_name = :first_name, last_name = :last_name,
                email = :email, updated_at = NOW() WHERE id = :id');
            $update->execute([
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'id' => $userId,
            ]);

            $doctor = $pdo->prepare('SELECT id FROM doctors WHERE user_id = :user_id LIMIT 1');
            $doctor->execute(['user_id' => $userId]);
            $doctorId = $doctor->fetchColumn();
            if ($role === 'medecin' && $doctorData !== null) {
                if ($doctorId !== false) {
                    $currentDoctor = $pdo->prepare('SELECT specialty_id, license_number FROM doctors WHERE id = :id');
                    $currentDoctor->execute(['id' => (int) $doctorId]);
                    $doctorProfile = $currentDoctor->fetch();
                    if (!is_array($doctorProfile) || (int) $doctorProfile['specialty_id'] !== (int) $doctorData['specialty_id']) {
                        $changedFields[] = 'Spécialité médicale';
                    }
                    if (!is_array($doctorProfile) || (string) $doctorProfile['license_number'] !== (string) $doctorData['license_number']) {
                        $changedFields[] = 'Licence médicale';
                    }
                    $saveDoctor = $pdo->prepare('UPDATE doctors SET specialty_id = :specialty_id, license_number = :license_number,
                        deleted_at = NULL, updated_at = NOW() WHERE id = :id');
                    $saveDoctor->execute($doctorData + ['id' => (int) $doctorId]);
                } else {
                    $changedFields[] = 'Profil médecin';
                    $saveDoctor = $pdo->prepare('INSERT INTO doctors (user_id, specialty_id, license_number, created_at)
                        VALUES (:user_id, :specialty_id, :license_number, NOW())');
                    $saveDoctor->execute($doctorData + ['user_id' => $userId]);
                }
            } elseif ($doctorId !== false) {
                $changedFields[] = 'Profil médecin';
                $deactivateDoctor = $pdo->prepare('UPDATE doctors SET deleted_at = COALESCE(deleted_at, NOW()), updated_at = NOW() WHERE id = :id');
                $deactivateDoctor->execute(['id' => (int) $doctorId]);
            }

            return $changedFields;
        });

        (new AuditLogger())->log('admin_staff_user_updated', 'user', $userId, $actorId, ['changes' => $changedFields]);
    }

    public function requestPasswordReset(int $userId, int $actorId): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT email FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL AND role <> "patient" LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $email = $stmt->fetchColumn();
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Compte staff actif avec e-mail valide introuvable.');
        }

        $link = (new AuthService())->createPasswordReset($email);
        (new AuditLogger())->log('admin_password_reset_requested', 'user', $userId, $actorId);
        return is_string($link) ? $link : null;
    }

    public function toggleUserStatus(int $userId, int $actorId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT role, is_active FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!is_array($user)) {
            throw new \InvalidArgumentException('Compte introuvable.');
        }
        if ($userId === $actorId && (int) $user['is_active'] === 1) {
            throw new \InvalidArgumentException('Vous ne pouvez pas désactiver votre propre compte.');
        }
        if ($user['role'] === 'admin' && (int) $user['is_active'] === 1) {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin" AND is_active = 1 AND deleted_at IS NULL')->fetchColumn();
            if ($count <= 1) {
                throw new \InvalidArgumentException('Le dernier compte administrateur actif ne peut pas être désactivé.');
            }
        }

        $update = $pdo->prepare('UPDATE users SET is_active = :is_active, updated_at = NOW() WHERE id = :id');
        $update->execute(['is_active' => (int) $user['is_active'] === 1 ? 0 : 1, 'id' => $userId]);
        $newStatus = (int) $user['is_active'] === 1 ? 'Désactivé' : 'Activé';
        (new AuditLogger())->log('admin_user_status_changed', 'user', $userId, $actorId, ['changes' => ['Statut du compte : ' . $newStatus]]);
    }

    public function createSpecialty(array $input, int $actorId): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150 || mb_strlen($description) > 2000) {
            throw new \InvalidArgumentException('Nom de spécialité invalide.');
        }

        $stmt = Database::pdo()->prepare('INSERT INTO specialties (name, description, created_at) VALUES (:name, :description, NOW())');
        try {
            $stmt->execute(['name' => $name, 'description' => $description !== '' ? $description : null]);
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new \InvalidArgumentException('Cette spécialité existe déjà.');
            }
            throw $exception;
        }
        (new AuditLogger())->log('admin_specialty_created', 'specialty', (int) Database::pdo()->lastInsertId(), $actorId, [
            'changes' => ['Spécialité « ' . $name . ' » créée'],
        ]);
    }

    public function createService(array $input, int $actorId): void
    {
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $price = filter_var($input['price_xaf'] ?? null, FILTER_VALIDATE_INT);
        if (preg_match('/\A[A-Z0-9_-]{2,40}\z/', $code) !== 1 || mb_strlen($name) < 2 || mb_strlen($name) > 190 || $price === false || $price < 0) {
            throw new \InvalidArgumentException('Code, libellé ou tarif invalide. Le tarif doit être un montant XAF positif.');
        }

        $stmt = Database::pdo()->prepare('INSERT INTO services (code, name, price_xaf, is_active, created_at)
            VALUES (:code, :name, :price_xaf, 1, NOW())');
        try {
            $stmt->execute(['code' => $code, 'name' => $name, 'price_xaf' => $price]);
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new \InvalidArgumentException('Ce code de tarif existe déjà.');
            }
            throw $exception;
        }
        (new AuditLogger())->log('admin_service_created', 'service', (int) Database::pdo()->lastInsertId(), $actorId, [
            'changes' => ['Acte « ' . $name . ' » créé au tarif de ' . number_format((int) $price, 0, ',', ' ') . ' XAF'],
        ]);
    }

    public function updateSpecialty(array $input, int $actorId): void
    {
        $id = (int) ($input['specialty_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        if ($id <= 0 || mb_strlen($name) < 2 || mb_strlen($name) > 150 || mb_strlen($description) > 2000) {
            throw new \InvalidArgumentException('Données de spécialité invalides.');
        }
        $existing = Database::pdo()->prepare('SELECT name, description FROM specialties WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $existing->execute(['id' => $id]);
        $current = $existing->fetch();
        if (!is_array($current)) {
            throw new \InvalidArgumentException('Spécialité introuvable.');
        }
        $changes = [];
        if ((string) $current['name'] !== $name) {
            $changes[] = 'Nom';
        }
        if ((string) ($current['description'] ?? '') !== $description) {
            $changes[] = 'Description';
        }
        $stmt = Database::pdo()->prepare('UPDATE specialties SET name = :name, description = :description, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        try {
            $stmt->execute(['name' => $name, 'description' => $description !== '' ? $description : null, 'id' => $id]);
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new \InvalidArgumentException('Cette spécialité existe déjà.');
            }
            throw $exception;
        }
        (new AuditLogger())->log('admin_specialty_updated', 'specialty', $id, $actorId, ['changes' => $changes]);
    }

    public function toggleSpecialtyStatus(int $specialtyId, int $actorId): void
    {
        $stmt = Database::pdo()->prepare('SELECT name, deleted_at FROM specialties WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $specialtyId]);
        $specialty = $stmt->fetch();
        if (!is_array($specialty)) {
            throw new \InvalidArgumentException('Spécialité introuvable.');
        }
        $update = Database::pdo()->prepare('UPDATE specialties SET deleted_at = :deleted_at, updated_at = NOW() WHERE id = :id');
        $isActive = $specialty['deleted_at'] !== null;
        $update->execute(['deleted_at' => $isActive ? null : date('Y-m-d H:i:s'), 'id' => $specialtyId]);
        (new AuditLogger())->log('admin_specialty_status_changed', 'specialty', $specialtyId, $actorId, [
            'changes' => ['Statut de « ' . $specialty['name'] . ' » : ' . ($isActive ? 'Activée' : 'Désactivée')],
        ]);
    }

    public function updateService(array $input, int $actorId): void
    {
        $id = (int) ($input['service_id'] ?? 0);
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $price = filter_var($input['price_xaf'] ?? null, FILTER_VALIDATE_INT);
        if ($id <= 0 || preg_match('/\A[A-Z0-9_-]{2,40}\z/', $code) !== 1 || mb_strlen($name) < 2 || mb_strlen($name) > 190 || $price === false || $price < 0) {
            throw new \InvalidArgumentException('Code, libellé ou tarif invalide.');
        }
        $existing = Database::pdo()->prepare('SELECT code, name, price_xaf FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $existing->execute(['id' => $id]);
        $current = $existing->fetch();
        if (!is_array($current)) {
            throw new \InvalidArgumentException('Tarif introuvable.');
        }
        $changes = [];
        if ((string) $current['code'] !== $code) {
            $changes[] = 'Code';
        }
        if ((string) $current['name'] !== $name) {
            $changes[] = 'Libellé';
        }
        if ((int) $current['price_xaf'] !== $price) {
            $changes[] = 'Tarif XAF';
        }
        $stmt = Database::pdo()->prepare('UPDATE services SET code = :code, name = :name, price_xaf = :price_xaf, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        try {
            $stmt->execute(['code' => $code, 'name' => $name, 'price_xaf' => $price, 'id' => $id]);
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new \InvalidArgumentException('Ce code tarifaire existe déjà.');
            }
            throw $exception;
        }
        (new AuditLogger())->log('admin_service_updated', 'service', $id, $actorId, ['changes' => $changes]);
    }

    public function toggleServiceStatus(int $serviceId, int $actorId): void
    {
        $pdo = Database::pdo();
        $current = $pdo->prepare('SELECT name, is_active FROM services WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $current->execute(['id' => $serviceId]);
        $service = $current->fetch();
        if (!is_array($service)) {
            throw new \InvalidArgumentException('Tarif introuvable.');
        }
        $stmt = $pdo->prepare('UPDATE services SET is_active = 1 - is_active, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $serviceId]);
        if ($stmt->rowCount() !== 1) {
            throw new \InvalidArgumentException('Tarif introuvable.');
        }
        (new AuditLogger())->log('admin_service_status_changed', 'service', $serviceId, $actorId, [
            'changes' => ['Statut de « ' . $service['name'] . ' » : ' . ((int) $service['is_active'] === 1 ? 'Désactivé' : 'Activé')],
        ]);
    }

    public function updateContactMessageStatus(int $messageId, string $status, int $actorId): void
    {
        if (!in_array($status, ['nouveau', 'lu', 'traite'], true)) {
            throw new \InvalidArgumentException('Statut de message invalide.');
        }
        $exists = Database::pdo()->prepare('SELECT status FROM contact_messages WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $messageId]);
        $previousStatus = $exists->fetchColumn();
        if ($previousStatus === false) {
            throw new \InvalidArgumentException('Message de contact introuvable.');
        }
        $stmt = Database::pdo()->prepare('UPDATE contact_messages SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $messageId]);
        $changes = (string) $previousStatus === $status ? [] : ['Statut du message : ' . $previousStatus . ' → ' . $status];
        (new AuditLogger())->log('admin_contact_message_updated', 'contact_message', $messageId, $actorId, ['changes' => $changes]);
    }

    public function savePublicSettings(array $input, int $actorId): void
    {
        $allowed = [
            'clinic_email' => 190,
            'clinic_phone' => 40,
            'clinic_address' => 255,
            'clinic_opening_hours' => 500,
            'appointment_cancel_min_hours' => 3,
            'appointment_validation_hours' => 3,
        ];
        $numericBounds = [
            'appointment_cancel_min_hours' => [0, 720],
            'appointment_validation_hours' => [1, 168],
        ];
        $values = [];
        foreach ($allowed as $key => $maxLength) {
            $value = trim((string) ($input[$key] ?? ''));
            if (mb_strlen($value) > $maxLength || ($key === 'clinic_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL))) {
                throw new \InvalidArgumentException('Valeur invalide pour le paramètre ' . $key . '.');
            }
            if (isset($numericBounds[$key])) {
                $number = filter_var($value, FILTER_VALIDATE_INT);
                [$minimum, $maximum] = $numericBounds[$key];
                if ($number === false || $number < $minimum || $number > $maximum) {
                    throw new \InvalidArgumentException('Le délai ' . $key . ' doit être compris entre ' . $minimum . ' et ' . $maximum . ' heures.');
                }
                $value = (string) $number;
            }
            $values[$key] = $value;
        }

        $existingSettings = Database::pdo()->query('SELECT setting_key, setting_value FROM settings
            WHERE setting_key IN ("clinic_email", "clinic_phone", "clinic_address", "clinic_opening_hours", "appointment_cancel_min_hours", "appointment_validation_hours")')
            ->fetchAll();
        $currentValues = array_column($existingSettings, 'setting_value', 'setting_key');
        $settingLabels = [
            'clinic_email' => 'E-mail de la clinique',
            'clinic_phone' => 'Téléphone',
            'clinic_address' => 'Adresse',
            'clinic_opening_hours' => 'Horaires d’ouverture',
            'appointment_cancel_min_hours' => 'Délai minimum d’annulation',
            'appointment_validation_hours' => 'Délai de validation',
        ];
        $changes = [];
        foreach ($values as $key => $value) {
            if ((string) ($currentValues[$key] ?? '') !== $value) {
                $changes[] = $settingLabels[$key];
            }
        }

        Database::transaction(function (\PDO $pdo) use ($values, $actorId): void {
            $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, value_type, updated_by, created_at)
                VALUES (:setting_key, :setting_value, "string", :updated_by, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()');
            foreach ($values as $key => $value) {
                $stmt->execute(['setting_key' => $key, 'setting_value' => $value, 'updated_by' => $actorId]);
            }
        });
        (new AuditLogger())->log('admin_public_settings_updated', 'settings', null, $actorId, ['changes' => $changes]);
    }
}
