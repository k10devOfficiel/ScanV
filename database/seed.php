<?php

declare(strict_types=1);

/**
 * ScanV Database Seeder CLI Runner
 */

define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(ROOT_PATH);

require_once ROOT_PATH . '/app/Helpers/helpers.php';
App\Core\Env::load(ROOT_PATH . '/.env');

use Database\Seeders\DemoSeeder;

echo "\n=========================================\n";
echo "         ScanV - Database Seeder\n";
echo "=========================================\n\n";

try {
    $seeder = new DemoSeeder();
    $result = $seeder->run();

    echo "\n-----------------------------------------\n";
    echo "Succès : Données de démonstration prêtes !\n";
    echo "Utilisateur : " . $result['user']->loginEmail . " (Mot de passe: Password123!)\n";
    echo "Carte public_id : " . $result['card']->publicId . "\n";
    echo "URL publique : " . url('/c/' . $result['card']->publicId) . "\n";
    echo "=========================================\n\n";
} catch (\Throwable $e) {
    echo "\n[ERREUR] : " . $e->getMessage() . "\n";
    exit(1);
}
