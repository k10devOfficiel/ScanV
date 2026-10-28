<?php

declare(strict_types=1);

/**
 * End-to-End HTTP Flow Test against live server:
 * Inscription -> Dashboard -> Déconnexion -> Reconnexion
 */

$baseUrl = 'http://localhost:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'scanv_cookie_');

function makeRequest(string $url, string $method = 'GET', array $postData = [], string $cookieFile = ''): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if ($cookieFile !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $rawResponse = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr((string)$rawResponse, 0, $headerSize);
    $body = substr((string)$rawResponse, $headerSize);

    curl_close($ch);

    return [
        'status' => $statusCode,
        'headers' => $headers,
        'body' => $body,
    ];
}

function extractCsrfToken(string $html): string
{
    if (preg_match('/name="_csrf_token"\s+value="([a-f0-9]{64})"/', $html, $matches)) {
        return $matches[1];
    }
    throw new RuntimeException("CSRF token not found in HTML form.");
}

echo "\n=======================================================\n";
echo "   ScanV - Test E2E du parcours complet (HTTP Live)\n";
echo "=======================================================\n\n";

try {
    // 1. GET /register
    echo "1. Visite de la page d'inscription (GET /register) ... ";
    $res = makeRequest($baseUrl . '/register', 'GET', [], $cookieJar);
    if ($res['status'] !== 200) {
        throw new RuntimeException("Expected 200 on /register, got " . $res['status']);
    }
    $csrfToken = extractCsrfToken($res['body']);
    echo "[OK] (Statut 200, Jeton CSRF extrait)\n";

    // 2. POST /register
    $uniqueEmail = 'e2e_' . bin2hex(random_bytes(4)) . '@scanv.test';
    $password = 'TestPass2026!';
    echo "2. Soumission de l'inscription pour {$uniqueEmail} ... ";
    $res = makeRequest($baseUrl . '/register', 'POST', [
        '_csrf_token' => $csrfToken,
        'first_name' => 'Camille',
        'last_name' => 'Laurent',
        'login_email' => $uniqueEmail,
        'password' => $password,
        'password_confirmation' => $password,
        'terms' => '1',
    ], $cookieJar);

    if ($res['status'] !== 302 || !str_contains($res['headers'], 'Location: http://localhost:8000/dashboard')) {
        throw new RuntimeException("Expected 302 to /dashboard, got " . $res['status'] . "\n" . $res['headers']);
    }
    echo "[OK] (Redirection 302 vers /dashboard reçue)\n";

    // 3. GET /dashboard
    echo "3. Affichage du tableau de bord (GET /dashboard) ... ";
    $res = makeRequest($baseUrl . '/dashboard', 'GET', [], $cookieJar);
    if ($res['status'] !== 200) {
        throw new RuntimeException("Expected 200 on /dashboard, got " . $res['status']);
    }
    if (!str_contains($res['body'], 'Camille Laurent') || !str_contains($res['body'], 'Identifiant public')) {
        throw new RuntimeException("Dashboard does not contain user name or card public id.");
    }
    $csrfLogout = extractCsrfToken($res['body']);
    echo "[OK] (Statut 200, Bienvenue Camille Laurent, carte créée visible)\n";

    // 4. POST /logout
    echo "4. Déconnexion (POST /logout) ... ";
    $res = makeRequest($baseUrl . '/logout', 'POST', [
        '_csrf_token' => $csrfLogout,
    ], $cookieJar);

    if ($res['status'] !== 302 || !str_contains($res['headers'], 'Location: http://localhost:8000/login')) {
        throw new RuntimeException("Expected 302 to /login on logout, got " . $res['status']);
    }
    echo "[OK] (Redirection 302 vers /login reçue)\n";

    // 5. GET /dashboard sans session
    echo "5. Vérification accès /dashboard sans session ... ";
    $res = makeRequest($baseUrl . '/dashboard', 'GET', [], $cookieJar);
    if ($res['status'] !== 302 || !str_contains($res['headers'], 'Location: http://localhost:8000/login')) {
        throw new RuntimeException("AuthMiddleware failed to block access without session.");
    }
    echo "[OK] (Accès bloqué, redirection 302 vers /login)\n";

    // 6. GET /login & extraction token
    echo "6. Visite de la page de connexion (GET /login) ... ";
    $res = makeRequest($baseUrl . '/login', 'GET', [], $cookieJar);
    if ($res['status'] !== 200) {
        throw new RuntimeException("Expected 200 on /login, got " . $res['status']);
    }
    $csrfLogin = extractCsrfToken($res['body']);
    echo "[OK] (Statut 200)\n";

    // 7. POST /login (Reconnexion)
    echo "7. Reconnexion avec {$uniqueEmail} (POST /login) ... ";
    $res = makeRequest($baseUrl . '/login', 'POST', [
        '_csrf_token' => $csrfLogin,
        'login_email' => $uniqueEmail,
        'password' => $password,
    ], $cookieJar);

    if ($res['status'] !== 302 || !str_contains($res['headers'], 'Location: http://localhost:8000/dashboard')) {
        throw new RuntimeException("Expected 302 to /dashboard on login, got " . $res['status']);
    }
    echo "[OK] (Redirection 302 vers /dashboard reçue)\n";

    // 8. GET /dashboard après reconnexion
    echo "8. Vérification finale du tableau de bord reconnecté ... ";
    $res = makeRequest($baseUrl . '/dashboard', 'GET', [], $cookieJar);
    if ($res['status'] !== 200 || !str_contains($res['body'], 'Camille Laurent')) {
        throw new RuntimeException("Failed to access dashboard after reconnecting.");
    }
    echo "[OK] (Session ré-établie avec succès)\n";

    echo "\n-------------------------------------------------------\n";
    echo "PARCOURS COMPLET VALIDÉ AVEC SUCCÈS À 100% !\n";
    echo "=======================================================\n\n";

    @unlink($cookieJar);
    exit(0);
} catch (\Throwable $e) {
    echo "\n[ECHEC] : " . $e->getMessage() . "\n";
    @unlink($cookieJar);
    exit(1);
}
