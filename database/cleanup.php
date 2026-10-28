<?php

declare(strict_types=1);

/**
 * ScanV - CLI Command for database cleanup (cron job).
 * Purges expired and consumed password reset OTPs and tokens.
 *
 * Usage:
 *   php database/cleanup.php
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(ROOT_PATH);

require_once ROOT_PATH . '/app/Helpers/helpers.php';
App\Core\Env::load(ROOT_PATH . '/.env');

use App\Core\Database;

echo "[" . gmdate('Y-m-d H:i:s') . " UTC] Démarrage du nettoyage ScanV...\n";

try {
    $pdo = Database::getConnection();

    // 1. Delete expired or consumed OTPs
    $stmtOtp = $pdo->prepare('
        DELETE FROM `password_reset_otps`
        WHERE `expires_at` < NOW()
           OR `consumed_at` IS NOT NULL
           OR `attempts` >= 5
    ');
    $stmtOtp->execute();
    $otpsDeleted = $stmtOtp->rowCount();

    // 2. Delete expired or used password reset tokens
    $stmtToken = $pdo->prepare('
        DELETE FROM `password_reset_tokens`
        WHERE `expires_at` < NOW()
           OR `used_at` IS NOT NULL
    ');
    $stmtToken->execute();
    $tokensDeleted = $stmtToken->rowCount();

    echo "Nettoyage terminé avec succès :\n";
    echo " - Codes OTP purgés : {$otpsDeleted}\n";
    echo " - Jetons de réinitialisation purgés : {$tokensDeleted}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erreur lors du nettoyage : " . $e->getMessage() . "\n");
    exit(1);
}
