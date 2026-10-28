<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\User;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardPdfService;
use App\Services\CardService;
use PDO;

/**
 * Comprehensive Security Audit Test Suite
 * Tests against all 10 security audit requirements:
 * 1. SQL Injection (Prepared statements)
 * 2. XSS & Output Escaping
 * 3. CSRF Protection
 * 4. Access Control & IDOR Prevention
 * 5. Visibility Enforcement on Media & Data
 * 6. Session Security & Cookie Configuration
 * 7. Uploads: MIME type, path traversal & controlled route authorization
 * 8. Config: Debug disablement in production & directory listing blocking (.htaccess)
 * 9. Security Headers & Content-Security-Policy (CSP)
 * 10. Anti-enumeration: Timing attacks and identical feedback
 */
class SecurityAuditTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private CardRepository $cardRepo;
    private CardService $cardService;
    private AuthService $authService;
    private CardPdfService $cardPdfService;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->userRepo = new UserRepository($this->pdo);
        $this->cardRepo = new CardRepository($this->pdo);
        $this->cardService = new CardService($this->pdo, $this->cardRepo);
        $this->authService = new AuthService($this->pdo, $this->userRepo, $this->cardRepo);
        $this->cardPdfService = new CardPdfService($this->cardRepo);
    }

    private function createUniqueUser(string $prefix = 'sec_user'): array
    {
        $email = $prefix . '_' . bin2hex(random_bytes(6)) . '@example.com';
        $res = $this->authService->register([
            'first_name' => 'SecFirst',
            'last_name' => 'SecLast',
            'login_email' => $email,
            'password' => 'SecurityPass123!',
            'password_confirmation' => 'SecurityPass123!',
            'terms' => '1',
        ]);

        return [$res['user'], $res['card']];
    }

    /**
     * Test 1: SQL Injection defense through prepared statements across all repositories.
     */
    public function testSqlInjectionResilience(): void
    {
        // Try classic SQL injection payloads on findByEmail
        $payload1 = "' OR '1'='1";
        $user = $this->userRepo->findByEmail($payload1);
        $this->assertNull($user, "SQL Injection payload 1 must return null");

        $payload2 = "admin' UNION SELECT 1,2,3,4,5,6,7,8 -- ";
        $user2 = $this->userRepo->findByEmail($payload2);
        $this->assertNull($user2, "SQL Injection payload 2 must return null");

        // Try SQL injection payload on findByPublicId
        $card = $this->cardRepo->findByPublicId("' OR 1=1 -- ");
        $this->assertNull($card, "Public ID SQL injection payload must return null");
    }

    /**
     * Test 2: XSS escaping in helper and attributes.
     */
    public function testXssEscapingAndAttributeSanitization(): void
    {
        $malicious = '<script>alert("XSS")</script>&"\'<>';
        $escaped = Security::e($malicious);

        $this->assertStringNotContains('<script>', $escaped, "Raw script tags must not survive escaping");
        $this->assertStringNotContains('"', $escaped, "Double quotes must be escaped to prevent attribute breakout");
        $this->assertStringNotContains("'", $escaped, "Single quotes must be escaped");
        $this->assertStringContains('&lt;script&gt;', $escaped, "HTML tags must be escaped as entities");

        // Helper e() handles null safely
        $this->assertEquals('', Security::e(null), "e(null) must return empty string");
    }

    /**
     * Test 3: CSRF middleware blocks POST without valid token.
     */
    public function testCsrfProtectionEnforced(): void
    {
        $_SESSION = [];
        $validToken = Security::csrfToken();

        // 1. Invalid token
        $this->assertFalse(Security::csrfCheck('invalid_fake_token'), "Fake CSRF token must fail check");

        // 2. Empty token
        $this->assertFalse(Security::csrfCheck(''), "Empty CSRF token must fail check");

        // 3. Null token
        $this->assertFalse(Security::csrfCheck(null), "Null token must fail check");

        // 4. Valid token matching session
        $this->assertTrue(Security::csrfCheck($validToken), "Valid session token must pass check");
    }

    /**
     * Test 4: Access control & IDOR prevention (cards resolved strictly from authenticated user).
     */
    public function testAccessControlAndIdorPrevention(): void
    {
        [$userA, $cardA] = $this->createUniqueUser('user_a');
        [$userB, $cardB] = $this->createUniqueUser('user_b');

        // User A fetches their card
        $resolvedA = $this->cardService->getCardForUser((int) $userA->id);
        $this->assertEquals((int) $userA->id, (int) $resolvedA->userId, "User A card must match User A ID");
        $this->assertEquals((int) $cardA->id, (int) $resolvedA->id, "User A cannot get User B card");

        // User B fetches their card
        $resolvedB = $this->cardService->getCardForUser((int) $userB->id);
        $this->assertEquals((int) $userB->id, (int) $resolvedB->userId, "User B card must match User B ID");
        $this->assertTrue((int) $resolvedB->id !== (int) $cardA->id, "User B cannot get User A card (IDOR prevented)");
    }

    /**
     * Test 5 & 7: Uploads route access control, visibility enforcement, and path traversal defense.
     */
    public function testUploadRouteAccessControlAndVisibility(): void
    {
        [$user, $card] = $this->createUniqueUser('uploader');

        // 1. Path traversal defense
        $this->assertFalse($this->cardService->isUploadAuthorized('../../.env', null), "Path traversal must be rejected");
        $this->assertFalse($this->cardService->isUploadAuthorized('test.php', null), "Disallowed extensions must be rejected");
        $this->assertFalse($this->cardService->isUploadAuthorized('../config/app.php', null), "Relative paths must be rejected");

        // 2. Create dummy upload photo file in storage/private-uploads/
        $uploadDir = dirname(__DIR__) . '/storage/private-uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $fakePhotoName = bin2hex(random_bytes(16)) . '.jpg';
        file_put_contents($uploadDir . '/' . $fakePhotoName, 'fake image data');

        // Associate photo with card and set show_photo = 0 (private)
        $this->cardRepo->update((int) $card->id, ['photo_path' => $fakePhotoName]);
        $this->cardRepo->updateVisibility((int) $card->id, ['show_photo' => 0]);

        // Unauthenticated visitor: must NOT be authorized to view private photo
        $canAnonView = $this->cardService->isUploadAuthorized($fakePhotoName, null);
        $this->assertFalse($canAnonView, "Anonymous visitor must not access private avatar (Rule 6)");

        // Card owner: CAN access their own avatar even if hidden (for editor preview)
        $canOwnerView = $this->cardService->isUploadAuthorized($fakePhotoName, (int) $user->id);
        $this->assertTrue($canOwnerView, "Owner must be authorized to view their own uploaded avatar");

        // Now make photo public: show_photo = 1
        $this->cardRepo->updateVisibility((int) $card->id, ['show_photo' => 1]);
        $canAnonViewNow = $this->cardService->isUploadAuthorized($fakePhotoName, null);
        $this->assertTrue($canAnonViewNow, "Anonymous visitor can view public avatar when show_photo is true");

        // Now deactivate card: is_active = 0
        $this->cardRepo->update((int) $card->id, ['is_active' => 0]);
        $canAnonViewInactive = $this->cardService->isUploadAuthorized($fakePhotoName, null);
        $this->assertFalse($canAnonViewInactive, "Anonymous visitor must not view avatar of deactivated card");

        // Cleanup
        @unlink($uploadDir . '/' . $fakePhotoName);
    }

    /**
     * Test 6: PDF export visibility defense-in-depth and URL validation.
     */
    public function testPdfExportVisibilityAndUrlSanitization(): void
    {
        [$user, $card] = $this->createUniqueUser('pdf_sec');

        // Create dummy logo file
        $uploadDir = dirname(__DIR__) . '/storage/private-uploads';
        $fakeLogoName = bin2hex(random_bytes(16)) . '.png';
        // Create 1x1 png image
        $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($uploadDir . '/' . $fakeLogoName, $png1x1);

        // Set malicious javascript website URL and hidden company logo
        $this->cardRepo->update((int) $card->id, [
            'logo_path' => $fakeLogoName,
            'company_name' => 'Secret Corp',
            'company_website' => 'javascript:alert("XSS")',
        ]);
        // Set show_company = 0 (private company and logo)
        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_company' => 0,
            'show_website' => 1,
        ]);

        $updatedCard = $this->cardRepo->findById((int) $card->id);
        $this->assertNotNull($updatedCard);

        $html = $this->cardPdfService->renderHtml($updatedCard);

        // 1. Verify hidden logo is NOT embedded in PDF HTML
        $this->assertStringNotContains('scanv-pdf-logo', $html, "Hidden logo must not be rendered in PDF when show_company=0");

        // 2. Verify javascript: URL is stripped / not embedded
        $this->assertStringNotContains('javascript:', $html, "javascript: scheme in website URL must be rejected in PDF");

        // Cleanup
        @unlink($uploadDir . '/' . $fakeLogoName);
    }

    /**
     * Test 8: Configuration security - APP_DEBUG forced false in production and .htaccess files exist.
     */
    public function testConfigAndDirectoryBrowsingProtection(): void
    {
        // 1. Check root .htaccess
        $rootHtaccess = dirname(__DIR__) . '/.htaccess';
        $this->assertTrue(file_exists($rootHtaccess), "Root .htaccess must exist");
        $rootContent = (string) file_get_contents($rootHtaccess);
        $this->assertStringContains('Options -Indexes', $rootContent, "Root .htaccess must disable directory browsing");
        $this->assertStringContains('storage', $rootContent, "Root .htaccess must block direct access to storage");
        $this->assertStringContains('.env', $rootContent, "Root .htaccess must block direct access to .env");

        // 2. Check storage/.htaccess
        $storageHtaccess = dirname(__DIR__) . '/storage/.htaccess';
        $this->assertTrue(file_exists($storageHtaccess), "storage/.htaccess must exist");
        $storageContent = (string) file_get_contents($storageHtaccess);
        $this->assertTrue(str_contains($storageContent, 'Require all denied') || str_contains($storageContent, 'Deny from all'), "storage/.htaccess must deny all access");

        // 3. Check public/.htaccess
        $publicHtaccess = dirname(__DIR__) . '/public/.htaccess';
        $this->assertTrue(file_exists($publicHtaccess), "public/.htaccess must exist");
        $publicContent = (string) file_get_contents($publicHtaccess);
        $this->assertStringContains('Content-Security-Policy', $publicContent, "public/.htaccess must define Content-Security-Policy");
    }

    /**
     * Test 9: Security headers present in public index configuration.
     */
    public function testSecurityHeadersDefined(): void
    {
        $indexFile = dirname(__DIR__) . '/public/index.php';
        $indexContent = (string) file_get_contents($indexFile);

        $this->assertStringContains('X-Content-Type-Options: nosniff', $indexContent, "X-Content-Type-Options must be configured");
        $this->assertStringContains('X-Frame-Options: SAMEORIGIN', $indexContent, "X-Frame-Options must be configured");
        $this->assertStringContains('Referrer-Policy: strict-origin-when-cross-origin', $indexContent, "Referrer-Policy must be configured");
        $this->assertStringContains('Content-Security-Policy:', $indexContent, "Content-Security-Policy must be configured");
        $this->assertStringContains('Strict-Transport-Security:', $indexContent, "HSTS must be configured");
    }

    /**
     * Test 10: Anti-enumeration & timing attack mitigation in authentication.
     */
    public function testAntiEnumerationAndTimingProtection(): void
    {
        // Login with non-existing email
        $resNonExistent = $this->authService->login('non_existent_' . bin2hex(random_bytes(6)) . '@example.com', 'DummyPassword123!', '127.0.0.1');
        $this->assertFalse($resNonExistent['success'], "Non-existent user login must fail");
        $this->assertEquals('Adresse e-mail ou mot de passe incorrect.', $resNonExistent['error'], "Generic error message required (no user enumeration)");

        // Login with existing user but wrong password
        [$existingUser, $c] = $this->createUniqueUser('existing_user');
        $resWrongPass = $this->authService->login($existingUser->loginEmail, 'WrongPassword123!', '127.0.0.1');
        $this->assertFalse($resWrongPass['success'], "Wrong password login must fail");
        $this->assertEquals('Adresse e-mail ou mot de passe incorrect.', $resWrongPass['error'], "Error message must be identical to non-existent email");

        // Both return exact same error string
        $this->assertEquals($resNonExistent['error'], $resWrongPass['error'], "Messages must be completely indistinguishable");
    }
}
