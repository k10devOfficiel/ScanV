<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Repository for managing password_reset_otps table.
 * - Stores HMAC-SHA256 hashed 6-digit codes.
 * - Tracks attempts, expiration, consumption, IP, and send rate.
 */
class PasswordResetOtpRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Create a new OTP entry for a user.
     */
    public function create(int $userId, string $codeHash, string $expiresAt, string $ip): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO `password_reset_otps` (
                `user_id`,
                `code_hash`,
                `attempts`,
                `expires_at`,
                `consumed_at`,
                `sent_at`,
                `ip`,
                `created_at`
            ) VALUES (
                :user_id,
                :code_hash,
                0,
                :expires_at,
                NULL,
                :sent_at,
                :ip,
                :created_at
            )
        ');

        $stmt->execute([
            ':user_id' => $userId,
            ':code_hash' => $codeHash,
            ':expires_at' => $expiresAt,
            ':sent_at' => $now,
            ':ip' => $ip,
            ':created_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Find latest active (unconsumed) OTP record for a user.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestActiveByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM `password_reset_otps`
            WHERE `user_id` = :user_id
              AND `consumed_at` IS NULL
            ORDER BY `id` DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Increment attempts count for an OTP record.
     * Returns the updated attempts count.
     */
    public function incrementAttempts(int $id): int
    {
        $stmt = $this->pdo->prepare('
            UPDATE `password_reset_otps`
            SET `attempts` = `attempts` + 1
            WHERE `id` = :id
        ');
        $stmt->execute([':id' => $id]);

        $stmt2 = $this->pdo->prepare('SELECT `attempts` FROM `password_reset_otps` WHERE `id` = :id');
        $stmt2->execute([':id' => $id]);
        return (int) $stmt2->fetchColumn();
    }

    /**
     * Mark an OTP record as consumed (single-use burned).
     */
    public function markConsumed(int $id): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE `password_reset_otps`
            SET `consumed_at` = :consumed_at
            WHERE `id` = :id
        ');
        return $stmt->execute([
            ':id' => $id,
            ':consumed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Invalidate all previous unconsumed OTPs for a user (e.g. when generating a new code).
     */
    public function invalidateAllForUser(int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE `password_reset_otps`
            SET `consumed_at` = :consumed_at
            WHERE `user_id` = :user_id
              AND `consumed_at` IS NULL
        ');
        return $stmt->execute([
            ':user_id' => $userId,
            ':consumed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Count recent OTP requests sent to a given email within the last N seconds (default 3600 = 1 hour).
     */
    public function countRecentSendsByEmail(string $email, int $seconds = 3600): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - $seconds);
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM `password_reset_otps` o
            INNER JOIN `users` u ON o.`user_id` = u.`id`
            WHERE u.`login_email` = :email
              AND o.`sent_at` >= :since
        ');
        $stmt->execute([
            ':email' => trim(strtolower($email)),
            ':since' => $since,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count recent OTP requests sent from a given IP address within the last N seconds (default 3600 = 1 hour).
     */
    public function countRecentSendsByIp(string $ip, int $seconds = 3600): int
    {
        $since = gmdate('Y-m-d H:i:s', time() - $seconds);
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM `password_reset_otps`
            WHERE `ip` = :ip
              AND `sent_at` >= :since
        ');
        $stmt->execute([
            ':ip' => $ip,
            ':since' => $since,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get Unix timestamp of the most recent OTP sent to a user.
     */
    public function getLatestSendTimeByUserId(int $userId): ?int
    {
        $stmt = $this->pdo->prepare('
            SELECT `sent_at` FROM `password_reset_otps`
            WHERE `user_id` = :user_id
            ORDER BY `id` DESC
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $userId]);
        $val = $stmt->fetchColumn();

        if ($val === false || $val === null) {
            return null;
        }

        return strtotime((string) $val . ' UTC');
    }

    /**
     * Get Unix timestamp of the most recent OTP sent from an IP.
     */
    public function getLatestSendTimeByIp(string $ip): ?int
    {
        $stmt = $this->pdo->prepare('
            SELECT `sent_at` FROM `password_reset_otps`
            WHERE `ip` = :ip
            ORDER BY `id` DESC
            LIMIT 1
        ');
        $stmt->execute([':ip' => $ip]);
        $val = $stmt->fetchColumn();

        if ($val === false || $val === null) {
            return null;
        }

        return strtotime((string) $val . ' UTC');
    }

    /**
     * Purge expired, consumed, or max-attempt OTP records (used by cleanup cron).
     */
    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM `password_reset_otps`
            WHERE `expires_at` < NOW()
               OR `consumed_at` IS NOT NULL
               OR `attempts` >= 5
        ');
        $stmt->execute();

        return $stmt->rowCount();
    }
}
