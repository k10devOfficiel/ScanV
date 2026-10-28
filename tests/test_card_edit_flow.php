<?php

declare(strict_types=1);

/**
 * End-to-end HTTP test: Card Edition and Reloading
 */

$baseUrl = 'http://localhost:8000';
$cookieJar = tempnam(sys_get_temp_dir(), 'card_edit_cookie_');

function httpReq(string $url, string $method = 'GET', array $postData = [], string $cookieFile = ''): array
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

function getCsrf(string $html): string
{
    if (preg_match('/name="_csrf_token"\s+value="([a-f0-9]{64})"/', $html, $m)) {
        return $m[1];
    }
    throw new RuntimeException("CSRF token not found in HTML.");
}

echo "\n=======================================================\n";
echo "   ScanV - Test E2E Édition & Rechargement de Carte\n";
echo "=======================================================\n\n";

try {
    // 1. Login with demo account
    echo "1. Connexion au compte de démonstration ... ";
    $resLogin = httpReq($baseUrl . '/login', 'GET', [], $cookieJar);
    $csrfLogin = getCsrf($resLogin['body']);

    $resAuth = httpReq($baseUrl . '/login', 'POST', [
        '_csrf_token' => $csrfLogin,
        'login_email' => 'demo@scanv.test',
        'password' => 'Password123!',
    ], $cookieJar);

    if ($resAuth['status'] !== 302) {
        throw new RuntimeException("Login failed, expected 302, got " . $resAuth['status']);
    }
    echo "[OK]\n";

    // 2. Open card editor GET /card/edit
    echo "2. Accès à l'éditeur de carte (GET /card/edit) ... ";
    $resEdit = httpReq($baseUrl . '/card/edit', 'GET', [], $cookieJar);
    if ($resEdit['status'] !== 200) {
        throw new RuntimeException("Expected 200 on /card/edit, got " . $resEdit['status']);
    }
    $csrfEdit = getCsrf($resEdit['body']);
    echo "[OK] (Statut 200, Jeton CSRF extrait)\n";

    // 3. Submit card updates
    echo "3. Enregistrement des modifications (POST /card/edit) ... ";
    $postPayload = [
        '_csrf_token' => $csrfEdit,
        'display_first_name' => 'Alexandre',
        'display_last_name' => 'Dupont-Moretti',
        'job_title' => 'Directeur Général',
        'department' => 'Direction Générale',
        'company_name' => 'ScanV International',
        'company_website' => 'https://scanv.com',
        'company_address' => '1 Rond-Point des Champs-Élysées, 75008 Paris',
        'professional_phone' => '06 99 88 77 66',
        'professional_email' => 'alexandre@scanv.com',
        'show_photo' => '1',
        'show_job_title' => '1',
        'show_department' => '1',
        'show_company' => '1',
        'show_company_address' => '1',
        'show_phone' => '1',
        'show_email' => '1',
        'show_website' => '1',
        'show_social_links' => '1',
        'links' => [
            [
                'label' => 'LinkedIn Pro',
                'url' => 'https://linkedin.com/in/alexandre-dupont',
                'is_public' => '1',
                'sort_order' => '1',
            ],
            [
                'label' => 'Site Web',
                'url' => 'https://alexandre.scanv.com',
                'is_public' => '1',
                'sort_order' => '2',
            ],
        ],
    ];

    $resUpdate = httpReq($baseUrl . '/card/edit', 'POST', $postPayload, $cookieJar);
    if ($resUpdate['status'] !== 302 || !str_contains($resUpdate['headers'], '/card/edit?saved=1')) {
        throw new RuntimeException("Expected 302 redirect to /card/edit?saved=1, got " . $resUpdate['status'] . "\n" . $resUpdate['headers']);
    }
    echo "[OK] (Redirection 302 vers /card/edit?saved=1 reçue)\n";

    // 4. Re-open /card/edit?saved=1 and verify data reloaded
    echo "4. Rechargement et vérification des données sauvegardées ... ";
    $resReloaded = httpReq($baseUrl . '/card/edit?saved=1', 'GET', [], $cookieJar);
    if ($resReloaded['status'] !== 200) {
        throw new RuntimeException("Expected 200, got " . $resReloaded['status']);
    }

    $body = $resReloaded['body'];
    $checks = [
        'Message de succès' => 'Votre carte de visite a été enregistrée avec succès.',
        'Nom modifié' => 'value="Dupont-Moretti"',
        'Poste modifié' => 'value="Directeur Général"',
        'Entreprise modifiée' => 'value="ScanV International"',
        'Téléphone modifié' => 'value="06 99 88 77 66"',
        'E-mail public modifié' => 'value="alexandre@scanv.com"',
        'Lien LinkedIn' => 'value="LinkedIn Pro"',
    ];

    foreach ($checks as $label => $needle) {
        if (!str_contains($body, $needle)) {
            throw new RuntimeException("Check failed for: {$label} (pattern '{$needle}' not found in reloaded HTML)");
        }
    }
    echo "[OK] (Toutes les valeurs ont été rechargées fidèlement)\n";

    echo "\n-------------------------------------------------------\n";
    echo "ÉDITION ET RECHARGEMENT VALIDÉS AVEC SUCCÈS À 100% !\n";
    echo "=======================================================\n\n";

    @unlink($cookieJar);
    exit(0);
} catch (\Throwable $e) {
    echo "\n[ERREUR] : " . $e->getMessage() . "\n\n";
    @unlink($cookieJar);
    exit(1);
}
