<?php

declare(strict_types=1);

namespace Cliniva\Services;

use Cliniva\Core\Database;

final class AuditLogger
{
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $userId = null,
        array $metadata = []
    ): void
    {
        if ($metadata !== []) {
            $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if (!is_string($encodedMetadata)) {
                throw new \RuntimeException('Impossible d’enregistrer les détails du journal d’audit.');
            }
        } else {
            $encodedMetadata = null;
        }

        $sql = 'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata, created_at)
                VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata, NOW())';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'metadata' => $encodedMetadata,
        ]);
    }
}
