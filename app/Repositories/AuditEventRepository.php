<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Repository for recording security and compliance events in audit_events table.
 */
class AuditEventRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Log an audit event.
     * Note: Plain passwords, tokens and raw secrets must NEVER be passed to this method (Rule 14 & F-18).
     * Non-identifying details (e.g. masked email a***@domaine.com) may be supplied.
     */
    public function log(string $eventType, ?int $userId = null, ?int $targetId = null, ?string $details = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO `audit_events` (`user_id`, `event_type`, `target_id`, `details`, `created_at`)
            VALUES (:user_id, :event_type, :target_id, :details, :created_at)
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':event_type' => $eventType,
            ':target_id' => $targetId,
            ':details' => $details,
            ':created_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
