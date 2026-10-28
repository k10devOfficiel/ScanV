<?php

declare(strict_types=1);

/**
 * End-to-end HTTP integration test for the 3-step OTP password reset flow
 * against the live dev server (http://localhost:8000).
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(ROOT_PATH);
App\Core\Env::load(ROOT_PATH . '/.env');

$pdo = App\Core\Database::getConnection();
$userRepo = new App\Repositories\UserRepository($pdo);
$demoUser = $userRepo->findByEmail('demo.reset@scanv.test');
if ($demoUser) {
    $pdo->exec("DELETE FROM password_reset_otps WHERE user_id = " . (int)$demoUser->id);
    $pdo->exec("DELETE FROM password_reset_tokens WHERE user_id = " . (int)$demoUser->id);
}

$baseUrl = 'http://localhost:8000';
$cookieFile = sys_get_temp_dir() . '/scanv_cookie_' . bin2hex(random_bytes(4)) . '.txt';

function httpGet(string $url, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr((string)$response, 0, $headerSize);
    $body = substr((string)$response, $headerSize);
    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $header, 'body' => $body];
}

function httpPost(string $url, array $data, string $cookieFile): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr((string)$response, 0, $headerSize);
    $body = substr((string)$response, $headerSize);
    curl_close($ch);
    return ['code' => $httpCode, 'headers' => $header, 'body' => $body];
}

function extractCsrf(string $html): ?string {
    if (preg_match('/name="_csrf_token"\s+value="([^"]+)"/i', $html, $m)) {
        return $m[1];
    }
    return null;
}

echo "=== SCANV OTP PASSWORD RESET E2E HTTP TEST ===\n\n";

// 1. GET /forgot
echo "1. GET /forgot...\n";
$getForgot = httpGet($baseUrl . '/forgot', $cookieFile);
if ($getForgot['code'] !== 200) {
    die("FAILED: GET /forgot returned HTTP {$getForgot['code']}\n");
}
$csrfForgot = extractCsrf($getForgot['body']);
echo "   Status: 200 OK | CSRF Token: {$csrfForgot}\n";

// 2. POST /forgot
$email = 'demo.reset@scanv.test';
echo "2. POST /forgot with email: {$email}...\n";
$postForgot = httpPost($baseUrl . '/forgot', [
    '_csrf_token' => $csrfForgot,
    'login_email' => $email,
], $cookieFile);
echo "   Status: {$postForgot['code']}\n";
if ($postForgot['code'] !== 302) {
    echo "Body: " . $postForgot['body'] . "\n";
    die("FAILED: Expected HTTP 302 redirect\n");
}
if (!str_contains($postForgot['headers'], '/forgot/verify')) {
    die("FAILED: Did not redirect to /forgot/verify\n");
}
echo "   Redirect Location: /forgot/verify [OK]\n";

// 3. GET /forgot/verify
echo "3. GET /forgot/verify...\n";
$getVerify = httpGet($baseUrl . '/forgot/verify', $cookieFile);
echo "   Status: {$getVerify['code']}\n";
if ($getVerify['code'] !== 200) {
    die("FAILED: GET /forgot/verify returned {$getVerify['code']}\n");
}
$csrfVerify = extractCsrf($getVerify['body']);
echo "   CSRF Token: {$csrfVerify}\n";
if (!str_contains($getVerify['body'], 'd***@scanv.test')) {
    echo "   Warning: Masked email expected d***@scanv.test\n";
} else {
    echo "   Masked email d***@scanv.test visible [OK]\n";
}

// 4. Retrieve OTP from storage/logs/mail.log
echo "4. Reading OTP from storage/logs/mail.log...\n";
$mailLog = ROOT_PATH . '/storage/logs/mail.log';
$logContent = file_get_contents($mailLog);
$entries = explode(str_repeat('=', 80), (string)$logContent);
$otp = null;
for ($i = count($entries) - 1; $i >= 0; $i--) {
    if (str_contains($entries[$i], "<{$email}>")) {
        if (preg_match('/Ton code de vérification\s*:\s*([0-9]{6})/i', $entries[$i], $m)) {
            $otp = $m[1];
            break;
        }
    }
}
if (!$otp) {
    die("FAILED: OTP code not found in mail.log for {$email}\n");
}
echo "   Latest OTP found in mail.log: {$otp} [OK]\n";

// 5. POST /forgot/verify
echo "5. POST /forgot/verify with OTP: {$otp}...\n";
$postVerify = httpPost($baseUrl . '/forgot/verify', [
    '_csrf_token' => $csrfVerify,
    'otp' => $otp,
], $cookieFile);
echo "   Status: {$postVerify['code']}\n";
if ($postVerify['code'] !== 302 || !str_contains($postVerify['headers'], '/forgot/new-password')) {
    echo "Headers: " . $postVerify['headers'] . "\n";
    die("FAILED: Expected redirect to /forgot/new-password\n");
}
echo "   Redirect Location: /forgot/new-password [OK]\n";

// 6. GET /forgot/new-password
echo "6. GET /forgot/new-password...\n";
$getNewPass = httpGet($baseUrl . '/forgot/new-password', $cookieFile);
echo "   Status: {$getNewPass['code']}\n";
if ($getNewPass['code'] !== 200) {
    die("FAILED: GET /forgot/new-password returned {$getNewPass['code']}\n");
}
$csrfNewPass = extractCsrf($getNewPass['body']);
echo "   CSRF Token: {$csrfNewPass}\n";

// 7. POST /forgot/new-password
$newSecret = 'BrandNewPassword2026!';
echo "7. POST /forgot/new-password to change password...\n";
$postNewPass = httpPost($baseUrl . '/forgot/new-password', [
    '_csrf_token' => $csrfNewPass,
    'password' => $newSecret,
    'password_confirmation' => $newSecret,
], $cookieFile);
echo "   Status: {$postNewPass['code']}\n";
if ($postNewPass['code'] !== 302 || !str_contains($postNewPass['headers'], '/login')) {
    echo "Headers: " . $postNewPass['headers'] . "\n";
    die("FAILED: Expected redirect to /login\n");
}
echo "   Redirect Location: /login [OK]\n";

// 8. Test Login with new password
echo "8. Logging in with new password...\n";
$getLogin = httpGet($baseUrl . '/login', $cookieFile);
$csrfLogin = extractCsrf($getLogin['body']);
$postLogin = httpPost($baseUrl . '/login', [
    '_csrf_token' => $csrfLogin,
    'login_email' => $email,
    'password' => $newSecret,
], $cookieFile);
echo "   Status: {$postLogin['code']}\n";
if ($postLogin['code'] !== 302 || !str_contains($postLogin['headers'], '/dashboard')) {
    die("FAILED: Login with new password did not redirect to /dashboard\n");
}
echo "   Redirect Location: /dashboard [OK]\n";

// Clean up
@unlink($cookieFile);
echo "\n============================================\n";
echo "SUCCESS: Full 3-step OTP journey completed!\n";
echo "============================================\n";
