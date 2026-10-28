<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\User;
use PDO;

/**
 * Repository for User entity data access.
 */
class UserRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Find a user by primary key ID.
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? User::fromArray($row) : null;
    }

    /**
     * Find a user by login email.
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `users` WHERE `login_email` = :email LIMIT 1');
        $stmt->execute([':email' => trim(strtolower($email))]);
        $row = $stmt->fetch();

        return $row ? User::fromArray($row) : null;
    }

    /**
     * Create a new user with hashed password and UTC timestamps.
     *
     * @param array{first_name: string, last_name: string, login_email: string, password: string, status?: string} $data
     */
    public function create(array $data): User
    {
        $now = gmdate('Y-m-d H:i:s');
        $hash = User::hashPassword($data['password']);

        $stmt = $this->pdo->prepare('
            INSERT INTO `users` (
                `first_name`,
                `last_name`,
                `login_email`,
                `password_hash`,
                `status`,
                `created_at`,
                `updated_at`
            ) VALUES (
                :first_name,
                :last_name,
                :login_email,
                :password_hash,
                :status,
                :created_at,
                :updated_at
            )
        ');

        $email = trim(strtolower($data['login_email']));
        $status = $data['status'] ?? 'active';

        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':login_email' => $email,
            ':password_hash' => $hash,
            ':status' => $status,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new User(
            id: $id,
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            loginEmail: $email,
            passwordHash: $hash,
            status: $status,
            createdAt: $now,
            updatedAt: $now
        );
    }

    /**
     * Update user details (name, email, status).
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id, ':updated_at' => gmdate('Y-m-d H:i:s')];

        if (isset($data['first_name'])) {
            $fields[] = '`first_name` = :first_name';
            $params[':first_name'] = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $fields[] = '`last_name` = :last_name';
            $params[':last_name'] = $data['last_name'];
        }

        if (isset($data['login_email'])) {
            $fields[] = '`login_email` = :login_email';
            $params[':login_email'] = trim(strtolower((string) $data['login_email']));
        }

        if (isset($data['status'])) {
            $fields[] = '`status` = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = '`updated_at` = :updated_at';
        $sql = 'UPDATE `users` SET ' . implode(', ', $fields) . ' WHERE `id` = :id';

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Update user password.
     */
    public function updatePassword(int $id, string $plainPassword): bool
    {
        $hash = User::hashPassword($plainPassword);
        $stmt = $this->pdo->prepare('
            UPDATE `users`
            SET `password_hash` = :hash, `updated_at` = :updated_at
            WHERE `id` = :id
        ');

        return $stmt->execute([
            ':id' => $id,
            ':hash' => $hash,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete user by ID (cascades in DB).
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM `users` WHERE `id` = :id');
        return $stmt->execute([':id' => $id]);
    }
}
