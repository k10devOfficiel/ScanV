<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Repository for password_resets table.
 */
class PasswordResetRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Store a new password reset token hash with expiration (1 hour, Rule 2 prepared queries).
     */
    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO `password_resets` (`user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`)
            VALUES (:user_id, :token_hash, :expires_at, NULL, :created_at)
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
     * Find reset record by sha256 token hash.
     *
     * @return array{id: int, user_id: int, token_hash: string, expires_at: string, used_at: ?string, created_at: string}|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM `password_resets`
            WHERE `token_hash` = :token_hash
            ORDER BY `id` DESC
            LIMIT 1
        ');
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'token_hash' => (string) $row['token_hash'],
            'expires_at' => (string) $row['expires_at'],
            'used_at' => $row['used_at'] !== null ? (string) $row['used_at'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * Mark a reset token as used (single use enforcement).
     */
    public function markUsed(int $id): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            UPDATE `password_resets`
            SET `used_at` = :used_at
            WHERE `id` = :id
        ');

        return $stmt->execute([
            ':id' => $id,
            ':used_at' => $now,
        ]);
    }

    /**
     * Invalidate all existing tokens for a user.
     */
    public function invalidateAllForUser(int $userId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            UPDATE `password_resets`
            SET `used_at` = :used_at
            WHERE `user_id` = :user_id AND `used_at` IS NULL
        ');

        return $stmt->execute([
            ':user_id' => $userId,
            ':used_at' => $now,
        ]);
    }
}
