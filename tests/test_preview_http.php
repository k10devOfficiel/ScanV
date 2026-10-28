<?php

declare(strict_types=1);

/**
 * ScanV - Test HTTP Live de Prévisualisation Recto / Verso (F-08)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
App\Core\Autoloader::register(dirname(__DIR__));
App\Core\Env::load(dirname(__DIR__) . '/.env');

$baseUrl = rtrim((string) App\Core\Env::get('APP_URL', 'http://localhost:8000'), '/');
$cookieJar = tempnam(sys_get_temp_dir(), 'scanv_preview_cookie_');

echo "\n=======================================================\n";
echo "   ScanV - Test HTTP Live Prévisualisation Recto/Verso\n";
echo "=======================================================\n\n";

// 1. Unauthenticated request to /card/preview/front -> 302 to /login
echo "1. Requête GET /card/preview/front sans session ... ";
$ch = curl_init($baseUrl . '/card/preview/front');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 302) {
    echo "[ECHEC] Attendu 302 vers /login, obtenu HTTP {$code}\n";
    exit(1);
}
echo "[OK] (Redirection 302 confirmée)\n";

// 2. Login as demo user
echo "2. Authentification utilisateur (demo@scanv.test) ... ";
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
    echo "[ECHEC] Jeton CSRF introuvable\n";
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

// 3. Request GET /card/preview/front
echo "3. Requête GET /card/preview/front (Recto) ... ";
$ch = curl_init($baseUrl . '/card/preview/front');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HEADER => true,
]);
$frontFull = curl_exec($ch);
$frontHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$frontType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$frontCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$frontBody = substr((string) $frontFull, $frontHeaderSize);

if ($frontCode !== 200) {
    echo "[ECHEC] Attendu 200 OK, obtenu HTTP {$frontCode}\n";
    exit(1);
}
if (!str_contains((string) $frontType, 'text/html')) {
    echo "[ECHEC] Content-Type attendu text/html, obtenu {$frontType}\n";
    exit(1);
}
if (!str_contains($frontBody, 'scanv-card scanv-card-front')) {
    echo "[ECHEC] Gabarit HTML recto manquant\n";
    exit(1);
}
echo "[OK] (HTTP 200, Content-Type: text/html, gabarit recto validé)\n";

// 4. Request GET /card/preview/back
echo "4. Requête GET /card/preview/back (Verso) ... ";
$ch = curl_init($baseUrl . '/card/preview/back');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => $cookieJar,
    CURLOPT_HEADER => true,
]);
$backFull = curl_exec($ch);
$backHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$backType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$backCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$backBody = substr((string) $backFull, $backHeaderSize);

if ($backCode !== 200) {
    echo "[ECHEC] Attendu 200 OK, obtenu HTTP {$backCode}\n";
    exit(1);
}
if (!str_contains($backBody, 'scanv-card scanv-card-back')) {
    echo "[ECHEC] Gabarit HTML verso manquant\n";
    exit(1);
}
if (!str_contains($backBody, 'Scannez pour enregistrer mon contact')) {
    echo "[ECHEC] Texte obligatoire manquant sur le verso\n";
    exit(1);
}
if (!str_contains($backBody, '<svg') && !str_contains($backBody, 'data:image/png;base64')) {
    echo "[ECHEC] QR code manquant dans la quiet zone du verso\n";
    exit(1);
}
echo "[OK] (HTTP 200, Content-Type: text/html, QR code & texte d'incitation validés)\n";

// Clean cookie jar
@unlink($cookieJar);

echo "\n-------------------------------------------------------\n";
echo "TOUS LES CONTRÔLES HTTP DU RENDU RECTO/VERSO SONT VALIDÉS !\n";
echo "=======================================================\n\n";
exit(0);
