<?php

declare(strict_types=1);

/**
 * ScanV Test Runner
 */

define('ROOT_PATH', dirname(__DIR__));

if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

require_once ROOT_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(ROOT_PATH);

require_once ROOT_PATH . '/app/Helpers/helpers.php';
App\Core\Env::load(ROOT_PATH . '/.env');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/RouterTest.php';
require_once __DIR__ . '/HelpersTest.php';
require_once __DIR__ . '/SecurityTest.php';
require_once __DIR__ . '/DatabaseTest.php';
require_once __DIR__ . '/AuthTest.php';
require_once __DIR__ . '/CardEditorTest.php';
require_once __DIR__ . '/PublicCardTest.php';
require_once __DIR__ . '/ContactExportTest.php';
require_once __DIR__ . '/QrCodeTest.php';
require_once __DIR__ . '/CardRenderTest.php';
require_once __DIR__ . '/CardExportPngTest.php';
require_once __DIR__ . '/CardExportPdfTest.php';
require_once __DIR__ . '/DashboardAndAccountTest.php';
require_once __DIR__ . '/SecurityAuditTest.php';
require_once __DIR__ . '/CardThemeTest.php';
require_once __DIR__ . '/PasswordResetOtpTest.php';

use Tests\RouterTest;
use Tests\HelpersTest;
use Tests\SecurityTest;
use Tests\DatabaseTest;
use Tests\AuthTest;
use Tests\CardEditorTest;
use Tests\PublicCardTest;
use Tests\ContactExportTest;
use Tests\QrCodeTest;
use Tests\CardRenderTest;
use Tests\CardExportPngTest;
use Tests\CardExportPdfTest;
use Tests\DashboardAndAccountTest;
use Tests\SecurityAuditTest;
use Tests\CardThemeTest;
use Tests\PasswordResetOtpTest;

$testClasses = [
    RouterTest::class,
    HelpersTest::class,
    SecurityTest::class,
    DatabaseTest::class,
    AuthTest::class,
    CardEditorTest::class,
    PublicCardTest::class,
    ContactExportTest::class,
    QrCodeTest::class,
    CardRenderTest::class,
    CardExportPngTest::class,
    CardExportPdfTest::class,
    DashboardAndAccountTest::class,
    SecurityAuditTest::class,
    CardThemeTest::class,
    PasswordResetOtpTest::class,
];

echo "\n=========================================\n";
echo "   ScanV - Exécution de la suite de tests\n";
echo "=========================================\n\n";

$totalTests = 0;
$totalAssertions = 0;
$totalFailures = 0;

foreach ($testClasses as $testClass) {
    echo "Classe : {$testClass}\n";
    $testInstance = new $testClass();
    $methods = get_class_methods($testInstance);

    foreach ($methods as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }

        $totalTests++;
        try {
            $testInstance->$method();
            echo "  [OK] {$method}\n";
        } catch (\Throwable $e) {
            echo "  [ERREUR] {$method}: " . $e->getMessage() . "\n";
            $totalFailures++;
        }
    }

    $totalAssertions += $testInstance->getAssertionsCount();
    $failures = $testInstance->getFailures();
    if (!empty($failures)) {
        foreach ($failures as $failure) {
            echo "  [ECHEC] {$failure}\n";
            $totalFailures++;
        }
    }
}

echo "\n-----------------------------------------\n";
echo "Résultat : {$totalTests} tests, {$totalAssertions} assertions, {$totalFailures} échecs.\n";

if ($totalFailures === 0) {
    echo "TOUS LES TESTS ONT RÉUSSI !\n";
    echo "=========================================\n\n";
    exit(0);
} else {
    echo "CERTAINS TESTS ONT ÉCHOUÉ.\n";
    echo "=========================================\n\n";
    exit(1);
}
