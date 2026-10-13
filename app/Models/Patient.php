<?php

declare(strict_types=1);

namespace Cliniva\Models;

final class Patient extends BaseModel
{
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM patients WHERE user_id = :user_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $patient = $stmt->fetch();

        return is_array($patient) ? $patient : null;
    }

    public function allergies(int $patientId): array
    {
        $stmt = $this->db->prepare('SELECT allergen, severity, notes FROM patient_allergies WHERE patient_id = :patient_id AND deleted_at IS NULL ORDER BY severity DESC, allergen');
        $stmt->execute(['patient_id' => $patientId]);

        return $stmt->fetchAll();
    }
}
