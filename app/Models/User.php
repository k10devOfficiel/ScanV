<?php

declare(strict_types=1);

namespace App\Models;

/**
 * User Entity Model.
 */
class User
{
    public function __construct(
        public readonly ?int $id,
        public string $firstName,
        public string $lastName,
        public string $loginEmail,
        public string $passwordHash,
        public string $status = 'active',
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {
    }

    /**
     * Create Model instance from DB row array.
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            firstName: (string) ($row['first_name'] ?? ''),
            lastName: (string) ($row['last_name'] ?? ''),
            loginEmail: (string) ($row['login_email'] ?? ''),
            passwordHash: (string) ($row['password_hash'] ?? ''),
            status: (string) ($row['status'] ?? 'active'),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    /**
     * Hash password using Argon2id if available, fallback to Bcrypt (Rule 4).
     */
    public static function hashPassword(string $plainPassword): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($plainPassword, PASSWORD_ARGON2ID);
        }
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    /**
     * Verify plain password against stored hash (Rule 4).
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    /**
     * Safe array representation (excluding passwordHash).
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'login_email' => $this->loginEmail,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
