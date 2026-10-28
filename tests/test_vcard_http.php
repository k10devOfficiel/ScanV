<?php

declare(strict_types=1);

/**
 * Live HTTP Test for GET /c/{public_id}/vcard.
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
echo "   ScanV - Test HTTP Téléchargement vCard (/vcard)\n";
echo "=======================================================\n\n";

try {
    $vcardUrl = $baseUrl . '/c/' . $card->publicId . '/vcard';
    echo "1. Requête GET {$vcardUrl} ... ";
    $res = httpGet($vcardUrl);

    if ($res['status'] !== 200) {
        throw new RuntimeException("Expected 200 OK, got {$res['status']}");
    }
    echo "[OK]\n";

    echo "2. Vérification des en-têtes HTTP vCard ... ";
    if (!str_contains($res['headers'], 'Content-Type: text/vcard; charset=utf-8')) {
        throw new RuntimeException("Missing or invalid Content-Type header");
    }
    if (!str_contains($res['headers'], 'Content-Disposition: attachment; filename=')) {
        throw new RuntimeException("Missing or invalid Content-Disposition header");
    }
    if (!str_contains($res['headers'], 'no-cache')) {
        throw new RuntimeException("Missing Cache-Control: no-cache header");
    }
    echo "[OK]\n";

    echo "3. Vérification du format vCard 3.0 ... ";
    if (!str_starts_with($res['body'], "BEGIN:VCARD\r\nVERSION:3.0\r\n")) {
        throw new RuntimeException("vCard body does not start with RFC 2426 header with CRLF");
    }
    if (!str_ends_with($res['body'], "END:VCARD\r\n")) {
        throw new RuntimeException("vCard body does not end with END:VCARD\\r\\n");
    }
    if (!str_contains($res['body'], "FN:")) {
        throw new RuntimeException("vCard missing FN property");
    }
    if (!str_contains($res['body'], "N:")) {
        throw new RuntimeException("vCard missing N property");
    }
    echo "[OK] (Format RFC 2426 vCard 3.0 valide avec CRLF)\n";

    echo "\n-------------------------------------------------------\n";
    echo "TÉLÉCHARGEMENT VCARD VALIDÉ AVEC SUCCÈS À 100% !\n";
    echo "=======================================================\n\n";
    exit(0);
} catch (\Throwable $e) {
    echo "\n[ERREUR] : " . $e->getMessage() . "\n\n";
    exit(1);
}
