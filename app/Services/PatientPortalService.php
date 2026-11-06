<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;
use PDO;

final class PatientPortalService
{
    private $audit;

    public function __construct()
    {
        $this->audit = new AuditLogger();
    }

    public function profile(int $userId): array
    {
        return ['patient' => $this->patient($userId)];
    }

    public function updateProfile(int $userId, array $input, array $photoUpload): void
    {
        $patient = $this->patient($userId);
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $errors = [];

        if (mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100) {
            $errors[] = 'Le prénom doit contenir entre 2 et 100 caractères.';
        }
        if (mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
            $errors[] = 'Le nom doit contenir entre 2 et 100 caractères.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Veuillez saisir une adresse e-mail valide.';
        }
        if ($phone !== '' && mb_strlen($phone) > 40) {
            $errors[] = 'Le numéro de téléphone ne peut pas dépasser 40 caractères.';
        }

        $newEmail = $email !== strtolower((string) $patient['account_email']);
        if ($newEmail) {
            $check = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :user_id AND deleted_at IS NULL');
            $check->execute(['email' => $email, 'user_id' => $userId]);
            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Cette adresse e-mail est déjà utilisée.';
            }
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode("\n", $errors));
        }

        $newPhotoPath = null;
        if (($photoUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newPhotoPath = $this->storeProfilePhoto($photoUpload);
        }

        try {
            Database::transaction(function (PDO $pdo) use ($userId, $patient, $firstName, $lastName, $email, $phone, $newEmail, $newPhotoPath): void {
                $photoPath = $newPhotoPath ?? ($patient['photo_path'] ?? null);
                $stmt = $pdo->prepare('UPDATE patients SET first_name = :first_name, last_name = :last_name,
                    email = :email, phone = :phone, photo_path = :photo_path, updated_at = NOW()
                    WHERE id = :patient_id AND user_id = :user_id AND deleted_at IS NULL');
                $stmt->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'photo_path' => $photoPath,
                    'patient_id' => (int) $patient['id'],
                    'user_id' => $userId,
                ]);

                $stmt = $pdo->prepare('UPDATE users SET first_name = :first_name, last_name = :last_name,
                    email = :email, phone = :phone, email_verified_at = :verified_at, updated_at = NOW()
                    WHERE id = :user_id AND deleted_at IS NULL');
                $stmt->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'verified_at' => $newEmail ? null : $patient['email_verified_at'],
                    'user_id' => $userId,
                ]);

                if ($newEmail) {
                    $token = random_token();
                    $verification = $pdo->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
                        VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())');
                    $verification->execute(['user_id' => $userId, 'token_hash' => hash_token($token)]);
                    $link = url('/verify-email?email=' . rawurlencode($email) . '&token=' . rawurlencode($token));
                    (new NotificationService())->queueEmail(
                        $userId,
                        (int) $patient['id'],
                        $email,
                        'Vérification de votre nouvelle adresse e-mail Cliniva',
                        "Confirmez votre nouvelle adresse e-mail : {$link}\n\nCe lien expire dans 24 heures."
                    );
                }
            });
        } catch (\Throwable $exception) {
            if ($newPhotoPath !== null) {
                $this->deleteStoredProfilePhoto($newPhotoPath);
            }
            throw $exception;
        }

        if ($newPhotoPath !== null && !empty($patient['photo_path']) && $patient['photo_path'] !== $newPhotoPath) {
            $this->deleteStoredProfilePhoto((string) $patient['photo_path']);
        }

        $updated = $patient;
        $updated['first_name'] = $firstName;
        $updated['last_name'] = $lastName;
        $updated['email'] = $email;
        $updated['role'] = 'patient';
        $updated['phone'] = $phone;
        $updated['email_verified_at'] = $newEmail ? null : $patient['email_verified_at'];
        login_user($updated);
    }

    public function profilePhotoPath(int $userId): string
    {
        $patient = $this->patient($userId);
        if (empty($patient['photo_path'])) {
            throw new \RuntimeException('Aucune photo de profil n’est enregistrée.');
        }

        $root = realpath(BASE_PATH . '/storage/uploads/patient-profiles');
        $path = realpath(BASE_PATH . '/storage/' . (string) $patient['photo_path']);
        if ($root === false || $path === false || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            throw new \RuntimeException('La photo de profil est introuvable.');
        }

        return $path;
    }

    public function documents(int $userId): array
    {
        $patient = $this->patient($userId);
        $patientId = (int) $patient['id'];
        $this->audit->log('patient_medical_documents_view', 'patient', $patientId, $userId);
        $pdo = Database::pdo();

        $documents = $pdo->prepare('SELECT id, original_name, category, mime_type, created_at
            FROM documents WHERE patient_id = :patient_id AND deleted_at IS NULL
            ORDER BY created_at DESC');
        $documents->execute(['patient_id' => $patientId]);

        $prescriptions = $pdo->prepare('SELECT p.id, p.prescription_number, p.created_at, u.first_name AS doctor_first_name, u.last_name AS doctor_last_name
            FROM prescriptions p
            INNER JOIN consultations c ON c.id = p.consultation_id
            INNER JOIN doctors d ON d.id = c.doctor_id
            INNER JOIN users u ON u.id = d.user_id
            WHERE c.patient_id = :patient_id AND c.deleted_at IS NULL
            ORDER BY p.created_at DESC');
        $prescriptions->execute(['patient_id' => $patientId]);
        $prescriptionRows = $prescriptions->fetchAll();
        $prescriptionItems = $pdo->prepare('SELECT pi.prescription_id, pi.medicine_name, pi.dosage, pi.frequency,
                pi.duration, pi.quantity, pi.quantity_unit, pi.instructions, pi.status,
                pi.medicine_form AS dosage_form, pi.medicine_strength AS strength
            FROM prescription_items pi
            INNER JOIN prescriptions p ON p.id = pi.prescription_id
            INNER JOIN consultations c ON c.id = p.consultation_id
            WHERE c.patient_id = :patient_id AND c.deleted_at IS NULL
            ORDER BY pi.prescription_id DESC, pi.id ASC');
        $prescriptionItems->execute(['patient_id' => $patientId]);
        $itemsByPrescription = [];
        foreach ($prescriptionItems->fetchAll() as $item) {
            $itemsByPrescription[(int) $item['prescription_id']][] = $item;
        }
        foreach ($prescriptionRows as &$prescription) {
            $prescription['items'] = $itemsByPrescription[(int) $prescription['id']] ?? [];
        }
        unset($prescription);

        $certificates = $pdo->prepare('SELECT cert.id, cert.certificate_number, cert.type, cert.created_at
            FROM certificates cert
            INNER JOIN consultations c ON c.id = cert.consultation_id
            WHERE c.patient_id = :patient_id AND c.deleted_at IS NULL
            ORDER BY cert.created_at DESC');
        $certificates->execute(['patient_id' => $patientId]);

        $results = $pdo->prepare('SELECT er.id, er.validated_at, er.created_at, req.label, req.type
            FROM exam_results er
            INNER JOIN exam_requests req ON req.id = er.exam_request_id
            INNER JOIN consultations c ON c.id = req.consultation_id
            WHERE c.patient_id = :patient_id AND c.deleted_at IS NULL
            ORDER BY COALESCE(er.validated_at, er.created_at) DESC');
        $results->execute(['patient_id' => $patientId]);

        $invoices = $pdo->prepare('SELECT id, invoice_number, status, total_xaf, issued_at, created_at
            FROM invoices WHERE patient_id = :patient_id AND deleted_at IS NULL
            ORDER BY created_at DESC');
        $invoices->execute(['patient_id' => $patientId]);

        return [
            'patient' => $patient,
            'documents' => $documents->fetchAll(),
            'prescriptions' => $prescriptionRows,
            'certificates' => $certificates->fetchAll(),
            'examResults' => $results->fetchAll(),
            'invoices' => $invoices->fetchAll(),
        ];
    }

    public function history(int $userId, int $page): array
    {
        $patient = $this->patient($userId);
        $patientId = (int) $patient['id'];
        $this->audit->log('patient_medical_history_view', 'patient', $patientId, $userId);
        $pageSize = 10;
        $page = min(1000000, max(1, $page));
        $offset = ($page - 1) * $pageSize;
        $pdo = Database::pdo();

        $count = $pdo->prepare('SELECT
            (SELECT COUNT(*) FROM appointments WHERE patient_id = :appointment_patient AND deleted_at IS NULL AND start_datetime < NOW()) +
            (SELECT COUNT(*) FROM consultations WHERE patient_id = :consultation_patient AND deleted_at IS NULL)');
        $count->execute(['appointment_patient' => $patientId, 'consultation_patient' => $patientId]);
        $total = (int) $count->fetchColumn();

        $sql = 'SELECT item_type, item_id, title, detail, happened_at FROM (
                SELECT "Rendez-vous" AS item_type, a.id AS item_id, s.name AS title,
                    CONCAT("Dr ", u.first_name, " ", u.last_name, " · ", a.status) AS detail,
                    a.start_datetime AS happened_at
                FROM appointments a
                INNER JOIN specialties s ON s.id = a.specialty_id
                INNER JOIN doctors d ON d.id = a.doctor_id
                INNER JOIN users u ON u.id = d.user_id
                WHERE a.patient_id = :appointment_patient AND a.deleted_at IS NULL AND a.start_datetime < NOW()
                UNION ALL
                SELECT "Consultation" AS item_type, c.id AS item_id, c.motive AS title,
                    CONCAT("Dr ", u.first_name, " ", u.last_name) AS detail, c.created_at AS happened_at
                FROM consultations c
                INNER JOIN doctors d ON d.id = c.doctor_id
                INNER JOIN users u ON u.id = d.user_id
                WHERE c.patient_id = :consultation_patient AND c.deleted_at IS NULL
            ) AS patient_history
            ORDER BY happened_at DESC
            LIMIT ' . $pageSize . ' OFFSET ' . $offset;
        $items = $pdo->prepare($sql);
        $items->execute(['appointment_patient' => $patientId, 'consultation_patient' => $patientId]);

        return [
            'patient' => $patient,
            'items' => $items->fetchAll(),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'pageCount' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    public function messages(int $userId): array
    {
        $patient = $this->patient($userId);
        $patientId = (int) $patient['id'];
        $stmt = Database::pdo()->prepare('SELECT pm.id, pm.appointment_id, pm.canal, pm.sujet, pm.corps,
                pm.lu_le, pm.created_at, u.first_name AS sender_first_name, u.last_name AS sender_last_name,
                a.start_datetime
            FROM patient_messages pm
            INNER JOIN users u ON u.id = pm.sender_id
            LEFT JOIN appointments a ON a.id = pm.appointment_id
            WHERE pm.patient_id = :patient_id
            ORDER BY pm.created_at DESC
            LIMIT 10');
        $stmt->execute(['patient_id' => $patientId]);
        $messages = $stmt->fetchAll();

        $unread = array_column(array_filter($messages, static function (array $message): bool {
            return $message['lu_le'] === null;
        }), 'id');
        if ($unread !== []) {
            $placeholders = implode(',', array_fill(0, count($unread), '?'));
            $markRead = Database::pdo()->prepare('UPDATE patient_messages SET lu_le = NOW()
                WHERE patient_id = ? AND lu_le IS NULL AND id IN (' . $placeholders . ')');
            $markRead->execute(array_merge([$patientId], $unread));
        }

        return $messages;
    }

    /** @return array{filename:string,mime:string,contents:string} */
    public function download(int $userId, string $type, int $id): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Document introuvable.');
        }

        $patient = $this->patient($userId);
        $patientId = (int) $patient['id'];

        if ($type === 'document') {
            $stmt = Database::pdo()->prepare('SELECT id, original_name, stored_name, mime_type
                FROM documents WHERE id = :id AND patient_id = :patient_id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $id, 'patient_id' => $patientId]);
            $document = $stmt->fetch();
            if (!is_array($document)) {
                throw new \RuntimeException('Document introuvable ou accès refusé.');
            }

            $storedName = (string) $document['stored_name'];
            if ($storedName === '' || basename($storedName) !== $storedName || preg_match('/\A[a-zA-Z0-9._-]+\z/', $storedName) !== 1) {
                throw new \RuntimeException('Le chemin du document est invalide.');
            }
            $path = realpath(BASE_PATH . '/storage/uploads/' . $storedName);
            $root = realpath(BASE_PATH . '/storage/uploads');
            if ($path === false || $root === false || strpos($path, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
                throw new \RuntimeException('Le fichier demandé est introuvable.');
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            if (!is_string($mime) || !in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain'], true)) {
                throw new \RuntimeException('Le type de fichier ne peut pas être téléchargé.');
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException('Le fichier demandé ne peut pas être lu.');
            }
            $this->audit->log('patient_document_export', 'document', $id, $userId);
            return [
                'filename' => $this->safeFilename((string) $document['original_name']),
                'mime' => $mime,
                'contents' => $contents,
            ];
        }

        $html = $this->generatedDocumentHtml($patientId, $type, $id);
        $filename = $html['filename'];

        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Le téléchargement PDF nécessite les dépendances Composer installées sur le serveur.');
        }

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html['html'], 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit->log('patient_document_export', $type, $id, $userId);
        return ['filename' => $filename, 'mime' => 'application/pdf', 'contents' => $dompdf->output()];
    }

    private function generatedDocumentHtml(int $patientId, string $type, int $id): array
    {
        $pdo = Database::pdo();
        $title = '';
        $rows = [];
        $filename = '';

        if ($type === 'prescription') {
            $stmt = $pdo->prepare('SELECT p.prescription_number, p.created_at, u.first_name, u.last_name
                FROM prescriptions p
                INNER JOIN consultations c ON c.id = p.consultation_id
                INNER JOIN doctors d ON d.id = c.doctor_id
                INNER JOIN users u ON u.id = d.user_id
                WHERE p.id = :id AND c.patient_id = :patient_id AND c.deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $id, 'patient_id' => $patientId]);
            $record = $stmt->fetch();
            if (!is_array($record)) {
                throw new \RuntimeException('Ordonnance introuvable ou accès refusé.');
            }
            $items = $pdo->prepare('SELECT medicine_name, medicine_form, medicine_strength, dosage, frequency,
                    duration, quantity, quantity_unit, instructions, status
                FROM prescription_items WHERE prescription_id = :id ORDER BY id ASC');
            $items->execute(['id' => $id]);
            foreach ($items->fetchAll() as $item) {
                $medicineLabel = (string) $item['medicine_name'];
                if (!empty($item['medicine_strength'])) {
                    $medicineLabel .= ' · ' . (string) $item['medicine_strength'];
                }
                if (!empty($item['medicine_form'])) {
                    $medicineLabel .= ' · ' . (string) $item['medicine_form'];
                }
                if ($item['status'] === 'annulee') {
                    $medicineLabel .= ' (ANNULÉE)';
                }
                $rows[] = '<tr><td>' . e($medicineLabel) . '</td><td>' . e($item['dosage']) . '</td><td>'
                    . e($item['frequency']) . '</td><td>' . e($item['duration']) . '</td><td>'
                    . e($item['quantity'] !== null ? $item['quantity'] . ' ' . $item['quantity_unit'] : '—')
                    . '</td><td>' . e($item['instructions']) . '</td></tr>';
            }
            $title = 'Ordonnance ' . $record['prescription_number'];
            array_unshift($rows, '<tr><th>Médicament</th><th>Dose</th><th>Posologie</th><th>Durée</th><th>Quantité</th><th>Instructions</th></tr>');
            $rows[] = '<tr><td colspan="6">Prescrit par Dr ' . e($record['first_name'] . ' ' . $record['last_name']) . '</td></tr>';
            $filename = $this->safeFilename((string) $record['prescription_number']) . '.pdf';
        } elseif ($type === 'certificate') {
            $stmt = $pdo->prepare('SELECT cert.certificate_number, cert.type, cert.content_encrypted, cert.created_at
                FROM certificates cert
                INNER JOIN consultations c ON c.id = cert.consultation_id
                WHERE cert.id = :id AND c.patient_id = :patient_id AND c.deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $id, 'patient_id' => $patientId]);
            $record = $stmt->fetch();
            if (!is_array($record)) {
                throw new \RuntimeException('Certificat introuvable ou accès refusé.');
            }
            $content = decrypt_sensitive($record['content_encrypted']);
            if (!is_string($content)) {
                throw new \RuntimeException('Le contenu du certificat ne peut pas être déchiffré.');
            }
            $title = 'Certificat ' . $record['certificate_number'];
            $rows[] = '<p>' . nl2br(e($content), false) . '</p>';
            $filename = $this->safeFilename((string) $record['certificate_number']) . '.pdf';
        } elseif ($type === 'exam-result') {
            $stmt = $pdo->prepare('SELECT er.result_encrypted, er.validated_at, req.label, req.type
                FROM exam_results er
                INNER JOIN exam_requests req ON req.id = er.exam_request_id
                INNER JOIN consultations c ON c.id = req.consultation_id
                WHERE er.id = :id AND c.patient_id = :patient_id AND c.deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $id, 'patient_id' => $patientId]);
            $record = $stmt->fetch();
            if (!is_array($record)) {
                throw new \RuntimeException('Résultat introuvable ou accès refusé.');
            }
            $content = decrypt_sensitive($record['result_encrypted']);
            if (!is_string($content)) {
                throw new \RuntimeException('Le résultat ne peut pas être déchiffré.');
            }
            $title = 'Résultat - ' . $record['label'];
            $rows[] = '<p>Type d’examen : ' . e($record['type']) . '</p><p>' . nl2br(e($content), false) . '</p>';
            $filename = 'resultat-examen-' . $id . '.pdf';
        } elseif ($type === 'invoice') {
            $stmt = $pdo->prepare('SELECT invoice_number, status, total_xaf, issued_at, created_at
                FROM invoices WHERE id = :id AND patient_id = :patient_id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $id, 'patient_id' => $patientId]);
            $record = $stmt->fetch();
            if (!is_array($record)) {
                throw new \RuntimeException('Facture introuvable ou accès refusé.');
            }
            $items = $pdo->prepare('SELECT label, quantity, unit_price_xaf, total_xaf
                FROM invoice_items WHERE invoice_id = :id ORDER BY id ASC');
            $items->execute(['id' => $id]);
            foreach ($items->fetchAll() as $item) {
                $rows[] = '<tr><td>' . e($item['label']) . '</td><td>' . e((int) $item['quantity']) . '</td><td>'
                    . e(number_format((int) $item['unit_price_xaf'], 0, ',', ' ')) . '</td><td>'
                    . e(number_format((int) $item['total_xaf'], 0, ',', ' ')) . ' XAF</td></tr>';
            }
            $title = 'Facture ' . $record['invoice_number'];
            $rows[] = '<tr><th colspan="3">Total</th><th>' . e(number_format((int) $record['total_xaf'], 0, ',', ' ')) . ' XAF</th></tr>';
            $filename = $this->safeFilename((string) $record['invoice_number']) . '.pdf';
        } else {
            throw new \InvalidArgumentException('Type de document inconnu.');
        }

        $patientStmt = $pdo->prepare('SELECT first_name, last_name, record_number FROM patients WHERE id = :id');
        $patientStmt->execute(['id' => $patientId]);
        $patient = $patientStmt->fetch();
        if (!is_array($patient)) {
            throw new \RuntimeException('Dossier patient introuvable.');
        }

        $content = in_array($type, ['prescription', 'invoice'], true)
            ? '<table><tbody>' . implode('', $rows) . '</tbody></table>'
            : implode('', $rows);
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,sans-serif;color:#14263d;font-size:12px}h1{font-size:20px}'
            . 'table{border-collapse:collapse;width:100%;margin-top:20px}th,td{border:1px solid #ccd5de;padding:8px;text-align:left}'
            . 'th{background:#edf2f6}</style></head><body><h1>Cliniva — ' . e($title) . '</h1>'
            . '<p>Patient : ' . e($patient['first_name'] . ' ' . $patient['last_name']) . ' (' . e($patient['record_number']) . ')</p>'
            . $content . '</body></html>';

        return ['filename' => $filename, 'html' => $html];
    }

    private function patient(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT p.*, u.role, u.email AS account_email, u.email_verified_at
            FROM patients p
            INNER JOIN users u ON u.id = p.user_id
            WHERE p.user_id = :user_id AND p.deleted_at IS NULL AND u.deleted_at IS NULL
            LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $patient = $stmt->fetch();
        if (!is_array($patient)) {
            throw new \RuntimeException('Aucun dossier patient associé à ce compte.');
        }

        return $patient;
    }

    private function storeProfilePhoto(array $upload): string
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            throw new \InvalidArgumentException('Le téléversement de la photo a échoué.');
        }
        if ((int) ($upload['size'] ?? 0) <= 0 || (int) $upload['size'] > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('La photo doit peser au maximum 5 Mo.');
        }
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new \RuntimeException('L’extension GD avec WebP doit être activée pour téléverser une photo.');
        }

        $tmpPath = (string) $upload['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        $imageInfo = getimagesize($tmpPath);
        $imageFactories = [
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        ];
        if (!is_string($mime) || !isset($imageFactories[$mime]) || !is_array($imageInfo)
            || $imageInfo[0] > 6000 || $imageInfo[1] > 6000 || $imageInfo[0] * $imageInfo[1] > 16000000) {
            throw new \InvalidArgumentException('Format de photo invalide. Utilisez JPEG, PNG ou WebP.');
        }

        $image = $imageFactories[$mime]($tmpPath);
        if ($image === false) {
            throw new \InvalidArgumentException('Le fichier image ne peut pas être lu.');
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1200 / max($width, $height));
        $resized = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        if ($resized === false) {
            imagedestroy($image);
            throw new \RuntimeException('La mémoire disponible ne permet pas de redimensionner la photo.');
        }
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 255, 255, 255, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
        imagedestroy($image);

        $relativeDirectory = 'uploads/patient-profiles';
        $directory = BASE_PATH . '/storage/' . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            imagedestroy($resized);
            throw new \RuntimeException('Le dossier sécurisé des photos ne peut pas être créé.');
        }

        $filename = bin2hex(random_bytes(24)) . '.webp';
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        $written = imagewebp($resized, $target, 82);
        imagedestroy($resized);
        if (!$written || !is_file($target)) {
            throw new \RuntimeException('La photo n’a pas pu être enregistrée.');
        }

        return $relativeDirectory . '/' . $filename;
    }

    private function deleteStoredProfilePhoto(string $relativePath): void
    {
        if (preg_match('#\Auploads/patient-profiles/[a-f0-9]{48}\.webp\z#', $relativePath) !== 1) {
            return;
        }
        $path = realpath(BASE_PATH . '/storage/' . $relativePath);
        $root = realpath(BASE_PATH . '/storage/uploads/patient-profiles');
        if ($path !== false && $root !== false && strpos($path, $root . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
            unlink($path);
        }
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim(preg_replace('/[^a-zA-Z0-9._-]+/', '-', $filename) ?? '', '.-_');
        return $filename !== '' ? $filename : 'document-cliniva';
    }
}
