<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PublicCardController;
use App\Core\Database;
use App\Core\Request;
use App\Models\BusinessCard;
use App\Models\User;
use App\Repositories\AuditEventRepository;
use App\Repositories\CardRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\AuthService;
use App\Services\CardImageService;
use App\Services\CardPdfService;
use App\Services\MailerService;
use App\Services\PasswordResetService;
use App\Services\PublicCardService;

/**
 * Test Suite for Dashboard and Management Functions:
 * 1. Dashboard (view dashboard/index)
 * 2. Deactivation / reactivation (F-17) & Public page status without data leakage
 * 3. Account deletion (F-18) with files purge and anonymized audit log
 * 4. Password reset (F-19) with token expiration and single-use enforcement
 */
class DashboardAndAccountTest extends TestCase
{
    private \PDO $pdo;
    private UserRepository $userRepo;
    private CardRepository $cardRepo;
    private PasswordResetRepository $resetRepo;
    private AuditEventRepository $auditRepo;
    private AuthService $authService;
    private AccountService $accountService;
    private PasswordResetService $resetService;
    private DashboardController $dashboardController;
    private AuthController $authController;
    private PublicCardController $publicCardController;
    private string $uploadDir;
    private string $generatedDir;
    private string $auditLog;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->userRepo = new UserRepository($this->pdo);
        $this->cardRepo = new CardRepository($this->pdo);
        $this->resetRepo = new PasswordResetRepository($this->pdo);
        $this->auditRepo = new AuditEventRepository($this->pdo);

        $root = dirname(__DIR__);
        $this->uploadDir = $root . '/storage/private-uploads';
        $this->generatedDir = $root . '/storage/generated';
        $this->auditLog = $root . '/storage/logs/audit.log';

        $this->authService = new AuthService($this->pdo, $this->userRepo, $this->cardRepo);
        $this->accountService = new AccountService(
            pdo: $this->pdo,
            userRepo: $this->userRepo,
            cardRepo: $this->cardRepo,
            auditRepo: $this->auditRepo,
            uploadDir: $this->uploadDir,
            generatedDir: $this->generatedDir,
            auditLogPath: $this->auditLog
        );

        $mailer = new MailerService(env: 'local');
        $this->resetService = new PasswordResetService(
            pdo: $this->pdo,
            userRepo: $this->userRepo,
            resetRepo: $this->resetRepo,
            mailerService: $mailer,
            auditRepo: $this->auditRepo
        );

        $this->dashboardController = new DashboardController(
            authService: $this->authService,
            cardRepo: $this->cardRepo,
            accountService: $this->accountService
        );

        $this->authController = new AuthController(
            authService: $this->authService,
            resetService: $this->resetService
        );

        $this->publicCardController = new PublicCardController(
            new PublicCardService($this->cardRepo)
        );
    }

    private function createTestUser(string $prefix, string $password = 'Password123!'): array
    {
        $unique = bin2hex(random_bytes(4));
        $email = $prefix . '_' . $unique . '@scanv.test';

        $reg = $this->authService->register([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'login_email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'terms' => '1',
        ]);

        $card = $this->cardRepo->findByUserId((int) $reg['user']->id);
        return [$reg['user'], $card];
    }

    /**
     * 1. Dashboard View renders correctly with state, links and previews.
     */
    public function testDashboardRendersCardStatusAndActions(): void
    {
        [$user, $card] = $this->createTestUser('dash_view');

        $_SESSION['user_id'] = $user->id;
        $req = new Request('GET', '/dashboard');
        $resp = $this->dashboardController->index($req);

        $this->assertEquals(200, $resp->getStatusCode());
        $html = $resp->getContent();

        $this->assertStringContains('Carte Active', $html);
        $this->assertStringContains('/card/edit', $html);
        $this->assertStringContains('/card/download/front.png', $html);
        $this->assertStringContains('/card/download/back.png', $html);
        $this->assertStringContains('/card/download/card.pdf', $html);
        $this->assertStringContains('/c/' . $card->publicId, $html);
        $this->assertStringContains('/card/preview/front', $html);
        $this->assertStringContains('/card/preview/back', $html);
    }

    /**
     * 2. Card Deactivation and Reactivation (F-17).
     * Tests: deactivating card then reloading public page returns 410 without any personal data,
     * and reactivating returns 200 with normal data.
     */
    public function testCardDeactivationAndReactivationOnPublicPage(): void
    {
        [$user, $card] = $this->createTestUser('dash_toggle');
        $_SESSION['user_id'] = $user->id;

        // 1. Initial state: public page is 200 OK
        $pubReq = new Request('GET', '/c/' . $card->publicId);
        $pubResp = $this->publicCardController->show($pubReq, $card->publicId);
        $this->assertEquals(200, $pubResp->getStatusCode(), "Active card must return 200 on public page.");
        $this->assertStringContains('Jean Dupont', $pubResp->getContent());

        // 2. Toggle to Deactivate (POST /card/toggle-status)
        $toggleReq = new Request('POST', '/card/toggle-status');
        $toggleResp = $this->dashboardController->toggleActive($toggleReq);
        $this->assertEquals(302, $toggleResp->getStatusCode(), "Toggle redirects back to dashboard.");

        $updatedCard = $this->cardRepo->findById((int) $card->id);
        $this->assertFalse($updatedCard->isActive, "Card isActive must now be false.");

        // 3. Reload public page -> Must return 410 Gone with neutral unavailable template
        $inactiveResp = $this->publicCardController->show($pubReq, $card->publicId);
        $this->assertEquals(410, $inactiveResp->getStatusCode(), "Deactivated card must return HTTP 410.");
        $inactiveHtml = $inactiveResp->getContent();

        $this->assertStringContains('Carte indisponible', $inactiveHtml);
        // Strict privacy check (Rule 6): no personal data leaks on unavailable page
        $this->assertStringNotContains('Jean Dupont', $inactiveHtml, "Deactivated card must not show user name.");
        $this->assertStringNotContains($user->loginEmail, $inactiveHtml, "Deactivated card must not show user email.");

        // 4. Reactivate card (POST /card/toggle-status)
        $toggleResp2 = $this->dashboardController->toggleActive($toggleReq);
        $this->assertEquals(302, $toggleResp2->getStatusCode());

        $reactivatedCard = $this->cardRepo->findById((int) $card->id);
        $this->assertTrue($reactivatedCard->isActive, "Card isActive must be true again.");

        // 5. Reload public page -> HTTP 200 with data restored
        $reactivatedResp = $this->publicCardController->show($pubReq, $card->publicId);
        $this->assertEquals(200, $reactivatedResp->getStatusCode(), "Reactivated card must return HTTP 200.");
        $this->assertStringContains('Jean Dupont', $reactivatedResp->getContent());
    }

    /**
     * 3. Account Deletion confirmation rejects wrong password (F-18).
     */
    public function testAccountDeletionRejectsInvalidPassword(): void
    {
        [$user, $card] = $this->createTestUser('del_fail', 'CorrectPass123!');
        $_SESSION['user_id'] = $user->id;

        $delReq = new Request('POST', '/account/delete', [], ['password' => 'WrongPassword!']);
        $resp = $this->dashboardController->deleteAccount($delReq);

        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertEquals(url('/dashboard'), $resp->getHeaders()['Location']);

        // User must still exist
        $stillUser = $this->userRepo->findById((int) $user->id);
        $this->assertNotNull($stillUser, "User must NOT be deleted with incorrect password.");
    }

    /**
     * 4. Account Deletion completely purges user, card, uploads, exports, and logs anonymously (F-18).
     */
    public function testAccountDeletionPurgesEverythingAndLogsAnonymously(): void
    {
        [$user, $card] = $this->createTestUser('del_full', 'SuperSecretPass123!');
        $_SESSION['user_id'] = $user->id;

        // Create mock uploaded avatar & logo files
        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }
        $mockPhoto = $this->uploadDir . '/avatar_test_' . $user->id . '.jpg';
        $mockLogo = $this->uploadDir . '/logo_test_' . $user->id . '.png';
        file_put_contents($mockPhoto, 'fake_photo_data');
        file_put_contents($mockLogo, 'fake_logo_data');

        $this->pdo->prepare("
            UPDATE business_cards
            SET photo_path = :photo, logo_path = :logo
            WHERE id = :id
        ")->execute([
            ':photo' => 'avatar_test_' . $user->id . '.jpg',
            ':logo' => 'logo_test_' . $user->id . '.png',
            ':id' => $card->id,
        ]);

        // Create mock generated cache files
        if (!is_dir($this->generatedDir)) {
            @mkdir($this->generatedDir, 0755, true);
        }
        $mockGeneratedPng = $this->generatedDir . '/card_' . $card->id . '_front_mock.png';
        $mockGeneratedPdf = $this->generatedDir . '/card_' . $card->id . '_pdf_mock.pdf';
        file_put_contents($mockGeneratedPng, 'fake_png_export');
        file_put_contents($mockGeneratedPdf, 'fake_pdf_export');

        // Execute Account Deletion with correct password
        $delReq = new Request('POST', '/account/delete', [], ['password' => 'SuperSecretPass123!']);
        $resp = $this->dashboardController->deleteAccount($delReq);

        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertEquals(url('/login'), $resp->getHeaders()['Location']);

        // 1. Database purge
        $deletedUser = $this->userRepo->findById((int) $user->id);
        $this->assertNull($deletedUser, "User record must be deleted.");

        $deletedCard = $this->cardRepo->findById((int) $card->id);
        $this->assertNull($deletedCard, "Business card record must be deleted.");

        // 2. Uploaded files purge
        $this->assertFalse(file_exists($mockPhoto), "Uploaded photo file must be unlinked.");
        $this->assertFalse(file_exists($mockLogo), "Uploaded logo file must be unlinked.");

        // 3. Generated cache files purge
        $this->assertFalse(file_exists($mockGeneratedPng), "Generated PNG export must be unlinked.");
        $this->assertFalse(file_exists($mockGeneratedPdf), "Generated PDF export must be unlinked.");

        // 4. Anonymized Audit log check (Rule 14 & F-18)
        $auditStmt = $this->pdo->query("SELECT * FROM audit_events WHERE event_type = 'account_deleted' ORDER BY id DESC LIMIT 1");
        $auditRow = $auditStmt->fetch();
        $this->assertNotEmpty($auditRow, "Anonymized audit event must be recorded in DB.");
        $this->assertNull($auditRow['user_id'], "Audit event user_id must be NULL after account deletion.");

        if (file_exists($this->auditLog)) {
            $logContent = (string) file_get_contents($this->auditLog);
            $this->assertStringNotContains($user->loginEmail, $logContent, "Audit log must not contain user's email.");
            $this->assertStringNotContains('SuperSecretPass123!', $logContent, "Audit log must never contain password.");
        }

        // 5. Session must be logged out
        $this->assertNull($this->authService->getCurrentUser(), "Session must be destroyed.");
    }

    /**
     * 5. Password Reset Request returns identical response whether email exists or not (anti-enumeration).
     */
    public function testPasswordResetRequestReturnsIdenticalResponseForExistingAndNonExistingEmail(): void
    {
        [$user, $card] = $this->createTestUser('reset_req');

        // Existing email
        $req1 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => '10.99.1.1']);
        $resp1 = $this->authController->forgot($req1);
        $this->assertEquals(302, $resp1->getStatusCode());
        $this->assertEquals(url('/forgot/verify'), $resp1->getHeaders()['Location']);
        $flashMsg1 = flash('info') ?? $_SESSION['flash']['info'] ?? '';
        $this->assertStringContains('Si un compte existe', $flashMsg1);

        // Non-existing email
        $req2 = new Request('POST', '/forgot', [], ['login_email' => 'does_not_exist_' . bin2hex(random_bytes(4)) . '@scanv.test'], ['REMOTE_ADDR' => '10.99.1.2']);
        $resp2 = $this->authController->forgot($req2);
        $this->assertEquals(302, $resp2->getStatusCode());
        $this->assertEquals(url('/forgot/verify'), $resp2->getHeaders()['Location']);
        $flashMsg2 = flash('info') ?? $_SESSION['flash']['info'] ?? '';
        $this->assertStringContains('Si un compte existe', $flashMsg2);
    }

    /**
     * 6. Direct access to /forgot/new-password without valid intermediate token is refused.
     */
    public function testPasswordResetDirectAccessWithoutTokenIsRefused(): void
    {
        unset($_SESSION['reset_token'], $_SESSION['reset_user_id']);

        $req = new Request('GET', '/forgot/new-password');
        $resp = $this->authController->showNewPassword($req);
        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertEquals(url('/forgot'), $resp->getHeaders()['Location']);
    }

    /**
     * 7. Password Reset Token Expiration Refused.
     */
    public function testPasswordResetExpiredTokenIsRefused(): void
    {
        [$user, $card] = $this->createTestUser('reset_exp');
        $tokenRepo = new \App\Repositories\PasswordResetTokenRepository($this->pdo);

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $pastDate = gmdate('Y-m-d H:i:s', time() - 3600); // 1 hour ago
        $tokenRepo->create((int) $user->id, $tokenHash, $pastDate);

        $_SESSION['reset_token'] = $plainToken;
        $_SESSION['reset_user_id'] = (int) $user->id;

        $getReq = new Request('GET', '/forgot/new-password');
        $getResp = $this->authController->showNewPassword($getReq);
        $this->assertEquals(302, $getResp->getStatusCode());
        $this->assertEquals(url('/forgot'), $getResp->getHeaders()['Location']);

        // Reset session token for POST test
        $_SESSION['reset_token'] = $plainToken;
        $_SESSION['reset_user_id'] = (int) $user->id;

        $postReq = new Request('POST', '/forgot/new-password', [], [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $postResp = $this->authController->newPassword($postReq);
        $this->assertEquals(422, $postResp->getStatusCode());
    }

    /**
     * 8. Password Reset successfully updates password and allows login with new credentials.
     */
    public function testPasswordResetAllowsLoginWithNewPasswordAndOldFails(): void
    {
        [$user, $card] = $this->createTestUser('reset_login', 'InitialPassword123!');
        $tokenRepo = new \App\Repositories\PasswordResetTokenRepository($this->pdo);

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $futureDate = gmdate('Y-m-d H:i:s', time() + 600);
        $tokenRepo->create((int) $user->id, $tokenHash, $futureDate);

        $_SESSION['reset_token'] = $plainToken;
        $_SESSION['reset_user_id'] = (int) $user->id;

        $req = new Request('POST', '/forgot/new-password', [], [
            'password' => 'UpdatedSecretPassword1!',
            'password_confirmation' => 'UpdatedSecretPassword1!',
        ]);
        $resp = $this->authController->newPassword($req);
        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertEquals(url('/login'), $resp->getHeaders()['Location']);

        // Old password fails
        $loginOld = $this->authService->login($user->loginEmail, 'InitialPassword123!', '127.0.0.1');
        $this->assertFalse($loginOld['success'], "Login with old password must fail.");

        // New password succeeds
        $loginNew = $this->authService->login($user->loginEmail, 'UpdatedSecretPassword1!', '127.0.0.1');
        $this->assertTrue($loginNew['success'], "Login with newly reset password must succeed.");
    }
}
