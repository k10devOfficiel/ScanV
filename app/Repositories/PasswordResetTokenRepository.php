<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Repository for managing password_reset_tokens table.
 * - Stores SHA256 hashed 32-byte reset tokens generated after successful OTP verification.
 * - Enforces 10-minute expiration and single-use.
 */
class PasswordResetTokenRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Create a new password reset token record.
     */
    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO `password_reset_tokens` (
                `user_id`,
                `token_hash`,
                `expires_at`,
                `used_at`,
                `created_at`
            ) VALUES (
                :user_id,
                :token_hash,
                :expires_at,
                NULL,
                :created_at
            )
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find a token record by its SHA256 hash.
     *
     * @return array<string, mixed>|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM `password_reset_tokens`
            WHERE `token_hash` = :token_hash
            LIMIT 1
        ');
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Mark a reset token as used (single-use burned).
     */
    public function markUsed(int $id): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE `password_reset_tokens`
            SET `used_at` = :used_at
            WHERE `id` = :id
        ');
        return $stmt->execute([
            ':id' => $id,
            ':used_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Invalidate all reset tokens for a user.
     */
    public function invalidateAllForUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE `password_reset_tokens`
            SET `used_at` = :used_at
            WHERE `user_id` = :user_id
              AND `used_at` IS NULL
        ');
        return $stmt->execute([
            ':user_id' => $userId,
            ':used_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete expired or used tokens (used by cleanup cron).
     */
    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM `password_reset_tokens`
            WHERE `expires_at` < NOW()
               OR `used_at` IS NOT NULL
        ');
        $stmt->execute();

        return $stmt->rowCount();
    }
}
