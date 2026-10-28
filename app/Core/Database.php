<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Centralized PDO Database connection manager (Singleton).
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
    }

    /**
     * Get the active PDO connection instance.
     *
     * @param array<string, mixed>|null $config Optional custom config array.
     */
    public static function getConnection(?array $config = null): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if ($config === null) {
            $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
            $config = $appConfig['database'] ?? [];
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $dbName = $config['database'] ?? 'scanv';
        $charset = $config['charset'] ?? 'utf8mb4';
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            return self::$instance;
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * Reset instance (useful for testing or reconnecting).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Set a custom PDO instance (useful for testing with SQLite in-memory or mock).
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
