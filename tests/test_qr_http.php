<?php

declare(strict_types=1);

/**
 * ScanV - Test HTTP Live du QR Code Privé (/card/qr.png)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register(dirname(__DIR__));
App\Core\Env::load(dirname(__DIR__) . '/.env');

use chillerlan\QRCode\QRCode as ChillerlanDecoder;

$baseUrl = rtrim((string) App\Core\Env::get('APP_URL', 'http://localhost:8000'), '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'scanv_cookie_');

echo "\n=======================================================\n";
echo "   ScanV - Test HTTP Live QR Code (/card/qr.png)\n";
echo "=======================================================\n\n";

// 1. Unauthenticated request to /card/qr.png -> must redirect to /login
echo "1. Requête GET /card/qr.png sans session ... ";
$ch = curl_init($baseUrl . '/card/qr.png');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
]);
$rawResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 302) {
    echo "[ECHEC] Attendu 302 Redirect vers /login, obtenu HTTP {$httpCode}\n";
    exit(1);
}
echo "[OK] (Redirection 302 vers /login confirmée)\n";

// 2. Login as demo user
echo "2. Authentification utilisateur (demo@scanv.test) ... ";
// First get CSRF token from /login
$ch = curl_init($baseUrl . '/login');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
]);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="_csrf_token"\s+value="([a-f0-9]+)"/i', (string) $loginHtml, $matches);
if (empty($matches[1])) {
    echo "[ECHEC] Jeton CSRF introuvable sur /login\n";
    exit(1);
}
$csrfToken = $matches[1];

$ch = curl_init($baseUrl . '/login');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        '_csrf_token' => $csrfToken,
        'login_email' => 'demo@scanv.test',
        'password' => 'Password123!',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIEJAR => $cookieJar,
    CURLOPT_COOKIEFILE => $cookieJar,
]);
$authHtml = curl_exec($ch);
$authStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($authStatus !== 200 || !str_contains((string) $authHtml, 'dashboard')) {
    echo "[ECHEC] Authentification échouée\n";
    exit(1);
}
echo "[OK]\n";

// 3. Request GET /card/qr.png with session cookie
echo "3. Requête GET /card/qr.png avec session authentifiée ... ";
$ch = curl_init($baseUrl . '/card/qr.png');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HEADER => true,
]);
$fullResponse = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$headers = substr((string) $fullResponse, 0, $headerSize);
$body = substr((string) $fullResponse, $headerSize);

if ($httpStatus !== 200) {
    echo "[ECHEC] Attendu 200 OK, obtenu HTTP {$httpStatus}\n";
    exit(1);
}
if (!str_starts_with((string) $contentType, 'image/png')) {
    echo "[ECHEC] Content-Type attendu image/png, obtenu : {$contentType}\n";
    exit(1);
}
if (!str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
    echo "[ECHEC] Le corps de la réponse ne commence pas par la signature binaire PNG\n";
    exit(1);
}
echo "[OK] (HTTP 200, Content-Type: image/png, signature PNG validée)\n";

// 4. Decode the served PNG image and verify the URL
echo "4. Décodage du QR code PNG servi ... ";
$decoder = new ChillerlanDecoder();
$decodedResult = $decoder->readFromBlob($body);
$decodedUrl = $decodedResult->data;

echo "URL décodée : {$decodedUrl} ... ";
if (!preg_match('~^' . preg_quote($baseUrl, '~') . '/c/[a-f0-9]{32}$~', $decodedUrl)) {
    echo "[ECHEC] L'URL décodée ne respecte pas le format attendu ({$baseUrl}/c/{public_id})\n";
    exit(1);
}
echo "[OK]\n";

// 5. Test with custom parameters ?size=350&margin=15&ec=Q
echo "5. Requête GET /card/qr.png?size=350&margin=15&ec=Q ... ";
$ch = curl_init($baseUrl . '/card/qr.png?size=350&margin=15&ec=Q');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
]);
$customPng = curl_exec($ch);
$customStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($customStatus !== 200) {
    echo "[ECHEC] Attendu 200 OK avec paramètres personnalisés, obtenu HTTP {$customStatus}\n";
    exit(1);
}
$customDecoded = (new ChillerlanDecoder())->readFromBlob((string) $customPng);
if ($customDecoded->data !== $decodedUrl) {
    echo "[ECHEC] L'URL décodée avec paramètres personnalisés ne correspond pas\n";
    exit(1);
}
echo "[OK] (Niveau Q et dimensions personnalisées validées)\n";

// Clean cookie jar
@unlink($cookieJar);

echo "\n-------------------------------------------------------\n";
echo "TOUS LES CONTRÔLES DU QR CODE SONT VALIDÉS AVEC SUCCÈS !\n";
echo "=======================================================\n\n";
exit(0);
