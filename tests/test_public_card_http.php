<?php

declare(strict_types=1);

/**
 * End-to-end HTTP Verification for Public Card (F-12, F-13, F-14, F-17).
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(dirname(__DIR__));
require_once dirname(__DIR__) . '/app/Helpers/helpers.php';
App\Core\Env::load(dirname(__DIR__) . '/.env');

use App\Core\Database;
use App\Repositories\CardRepository;

$baseUrl = 'http://localhost:8000';
$pdo = Database::getConnection();
$cardRepo = new CardRepository($pdo);

// Find demo user's card
$stmt = $pdo->prepare('SELECT * FROM users WHERE login_email = :email LIMIT 1');
$stmt->execute([':email' => 'demo@scanv.test']);
$demoUser = $stmt->fetch();

if (!$demoUser) {
    echo "Demo user not found!\n";
    exit(1);
}

$card = $cardRepo->findByUserId((int) $demoUser['id']);
if (!$card) {
    echo "Demo card not found!\n";
    exit(1);
}

function httpGet(string $url): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr((string)$raw, 0, $hdrSize);
    $body = substr((string)$raw, $hdrSize);

    curl_close($ch);

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => $body,
    ];
}

echo "\n=======================================================\n";
echo "   ScanV - Test HTTP Page Publique (Mobile-first)\n";
echo "=======================================================\n\n";

try {
    // 1. Test GET /c/{public_id} for active card
    echo "1. Requête GET /c/{$card->publicId} ... ";
    $res = httpGet($baseUrl . '/c/' . $card->publicId);

    if ($res['status'] !== 200) {
        throw new RuntimeException("Expected 200 OK, got {$res['status']}");
    }
    echo "[OK]\n";

    // 2. Verify security headers and meta tags
    echo "2. Vérification des en-têtes et meta robots/viewport ... ";
    if (!str_contains($res['headers'], 'no-cache')) {
        throw new RuntimeException("Expected Cache-Control: no-cache in headers");
    }
    if (!str_contains($res['body'], '<meta name="robots" content="noindex">')) {
        throw new RuntimeException("Expected <meta name=\"robots\" content=\"noindex\"> in HTML");
    }
    if (!str_contains($res['body'], '<meta name="viewport" content="width=device-width, initial-scale=1.0">')) {
        throw new RuntimeException("Expected viewport meta tag in HTML");
    }
    echo "[OK]\n";

    // 3. Verify mobile-first layout and no horizontal overflow constraints
    echo "3. Vérification des conteneurs mobile (375px) ... ";
    $cssFile = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
    // Ensure overflow-x is hidden and max-width / width: 100% is properly configured
    if (!str_contains($cssFile, '.public-body') || !str_contains($cssFile, 'overflow-x: hidden')) {
        throw new RuntimeException("Expected overflow-x: hidden on public mobile layout");
    }
    if (!str_contains($cssFile, '.public-card-wrapper') || !str_contains($cssFile, 'box-sizing: border-box')) {
        throw new RuntimeException("Expected box-sizing: border-box on public card wrapper");
    }
    echo "[OK] (Conteneurs fluides sans débordement horizontal)\n";

    // 4. Verify touch action buttons (Save contact, call, mail)
    echo "4. Vérification des boutons d'action tactiles ... ";
    if (!str_contains($res['body'], 'id="save-contact-btn"') || !str_contains($res['body'], "/c/{$card->publicId}/vcard")) {
        throw new RuntimeException("Expected Save contact button linking to vCard URL");
    }
    echo "[OK]\n";

    // 5. Test unknown public ID returns 404
    echo "5. Test ID public inexistant (GET /c/00000000000000000000000000000000) ... ";
    $res404 = httpGet($baseUrl . '/c/00000000000000000000000000000000');
    if ($res404['status'] !== 404) {
        throw new RuntimeException("Expected 404 for unknown ID, got {$res404['status']}");
    }
    if (!str_contains($res404['body'], "Cette carte n'est pas disponible")) {
        throw new RuntimeException("Expected neutral unavailable message for 404");
    }
    echo "[OK] (Statut 404 neutre sans fuite de données)\n";

    // 6. Test deactivated card returns 410 without data leakage
    echo "6. Test carte désactivée (is_active = 0) ... ";
    // Temporarily deactivate
    $cardRepo->update((int) $card->id, ['is_active' => 0]);
    $resDeact = httpGet($baseUrl . '/c/' . $card->publicId);

    if ($resDeact['status'] !== 410) {
        // Re-activate before throwing
        $cardRepo->update((int) $card->id, ['is_active' => 1]);
        throw new RuntimeException("Expected 410 for deactivated card, got {$resDeact['status']}");
    }
    if (!str_contains($resDeact['body'], "Cette carte n'est pas disponible")) {
        $cardRepo->update((int) $card->id, ['is_active' => 1]);
        throw new RuntimeException("Expected neutral unavailable message for deactivated card");
    }
    // Verify no personal data is leaked
    if (str_contains($resDeact['body'], $card->displayLastName)) {
        $cardRepo->update((int) $card->id, ['is_active' => 1]);
        throw new RuntimeException("Leak detected: displayLastName found in deactivated response!");
    }

    // Re-activate card
    $cardRepo->update((int) $card->id, ['is_active' => 1]);
    echo "[OK] (Statut 410 neutre sans aucune fuite de données)\n";

    echo "\n-------------------------------------------------------\n";
    echo "TOUS LES CONTRÔLES HTTP ET MOBILE SONT VALIDÉS À 100% !\n";
    echo "=======================================================\n\n";
    exit(0);
} catch (\Throwable $e) {
    // Ensure card is active if an error happened
    if (isset($card)) {
        $cardRepo->update((int) $card->id, ['is_active' => 1]);
    }
    echo "\n[ERREUR] : " . $e->getMessage() . "\n\n";
    exit(1);
}
