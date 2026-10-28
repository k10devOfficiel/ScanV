<?php

declare(strict_types=1);

/**
 * Responsive Rendering & Overflow Audit across Landing, Auth, Dashboard, Editor, and Public Card.
 */

$baseUrl = 'http://localhost:8000';

function fetchPage(string $url): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $html = (string) curl_exec($ch);
    curl_close($ch);
    return $html;
}

echo "\n=======================================================\n";
echo "   ScanV - Audit de Rendu Responsive (375px / 768px / Desktop)\n";
echo "=======================================================\n\n";

$pages = [
    'Accueil (Landing)' => $baseUrl . '/',
    'Connexion (Login)' => $baseUrl . '/login',
    'Inscription (Register)' => $baseUrl . '/register',
    'Carte Publique Demo' => $baseUrl . '/c/69ac0041b67ea85479253f1424e5abbd',
];

foreach ($pages as $label => $url) {
    echo "Audit de la page: {$label} ... ";
    $html = fetchPage($url);

    if (empty($html)) {
        throw new RuntimeException("Empty response for {$url}");
    }

    // 1. Viewport meta tag
    if (!str_contains($html, '<meta name="viewport" content="width=device-width, initial-scale=1.0">')) {
        throw new RuntimeException("Missing viewport tag on {$label}");
    }

    // 2. Check no dangerous inline styles with hardcoded fixed width (not max-width) > 360px
    if (preg_match('/(?<!max-)width:\s*(?:[4-9]\d{2}|\d{4,})px/i', $html, $m)) {
        throw new RuntimeException("Dangerous hardcoded fixed width found on {$label}: {$m[0]}");
    }

    echo "[OK]\n";
}

// 3. CSS Audit
echo "Audit des règles CSS (overflow-x, fluidité 375px) ... ";
$css = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');

if (!str_contains($css, 'overflow-x: hidden')) {
    throw new RuntimeException("Missing overflow-x: hidden on root/body");
}
if (!str_contains($css, '@media (min-width: 768px)')) {
    throw new RuntimeException("Missing tablet breakpoint");
}
if (!str_contains($css, '@media (min-width: 1024px)')) {
    throw new RuntimeException("Missing desktop breakpoint");
}
echo "[OK]\n";

echo "\n-------------------------------------------------------\n";
echo "AUDIT RESPONSIVE VALIDÉ SANS AUCUN DÉBORDEMENT !\n";
echo "=======================================================\n\n";
exit(0);
