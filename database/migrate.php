<?php

declare(strict_types=1);

/**
 * ScanV Database Migration CLI Runner
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(ROOT_PATH);

require_once ROOT_PATH . '/app/Helpers/helpers.php';
App\Core\Env::load(ROOT_PATH . '/.env');

$config = require ROOT_PATH . '/config/app.php';
$dbConfig = $config['database'] ?? [];

$host = $dbConfig['host'] ?? '127.0.0.1';
$port = (int) ($dbConfig['port'] ?? 3306);
$dbName = $dbConfig['database'] ?? 'scanv';
$user = $dbConfig['username'] ?? 'root';
$pass = $dbConfig['password'] ?? '';
$charset = $dbConfig['charset'] ?? 'utf8mb4';

echo "\n=========================================\n";
echo "       ScanV - Migration Database\n";
echo "=========================================\n\n";

try {
    // 1. Connect to MySQL server (without DB) to ensure database exists
    $pdoRoot = new PDO(
        "mysql:host={$host};port={$port};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Create database if it does not exist
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Base de données '{$dbName}' vérifiée/créée avec succès.\n";

    // 2. Connect to the target database
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ]
    );

    // 3. Ensure migrations tracking table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `applied_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_migrations_migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Retrieve already applied migrations
    $stmt = $pdo->query("SELECT `migration` FROM `migrations`");
    $appliedMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // 5. Scan migration files
    $migrationsDir = __DIR__ . '/migrations';
    $files = glob($migrationsDir . '/*.sql');
    sort($files);

    $pendingMigrations = [];
    foreach ($files as $filePath) {
        $filename = basename($filePath);
        if (!in_array($filename, $appliedMigrations, true)) {
            $pendingMigrations[$filename] = $filePath;
        }
    }

    if (empty($pendingMigrations)) {
        echo "\nAucune nouvelle migration à exécuter.\nToutes les migrations sont déjà à jour (" . count($appliedMigrations) . " appliquées).\n\n";
        exit(0);
    }

    echo "Migrations à appliquer : " . count($pendingMigrations) . "\n\n";

    // 6. Execute pending migrations in sequence
    $appliedCount = 0;
    foreach ($pendingMigrations as $filename => $filePath) {
        echo "Exécution de : {$filename} ... ";
        $sql = file_get_contents($filePath);
        if ($sql === false || trim($sql) === '') {
            echo "VIDE (ignoré)\n";
            continue;
        }

        // Execute SQL statements
        $pdo->exec($sql);

        // Record in migrations table (UTC timestamp)
        $nowUtc = gmdate('Y-m-d H:i:s');
        $insertStmt = $pdo->prepare("INSERT INTO `migrations` (`migration`, `applied_at`) VALUES (:migration, :applied_at)");
        $insertStmt->execute([
            ':migration' => $filename,
            ':applied_at' => $nowUtc,
        ]);

        echo "[OK]\n";
        $appliedCount++;
    }

    echo "\n-----------------------------------------\n";
    echo "Succès : {$appliedCount} migration(s) appliquée(s) avec succès.\n";
    echo "=========================================\n\n";

} catch (\Throwable $e) {
    echo "\n[ERREUR FATALE] : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}
