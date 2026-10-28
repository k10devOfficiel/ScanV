<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\AuthController;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\AuditEventRepository;
use App\Repositories\CardRepository;
use App\Repositories\PasswordResetOtpRepository;
use App\Repositories\PasswordResetTokenRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\PasswordResetOtpService;

class PasswordResetOtpTest extends TestCase
{
    private \PDO $pdo;
    private UserRepository $userRepo;
    private CardRepository $cardRepo;
    private PasswordResetOtpRepository $otpRepo;
    private PasswordResetTokenRepository $tokenRepo;
    private AuditEventRepository $auditRepo;
    private AuthService $authService;
    private MailService $mailService;
    private PasswordResetOtpService $otpService;
    private AuthController $authController;
    private string $mailLog;
    private string $auditLog;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->userRepo = new UserRepository($this->pdo);
        $this->cardRepo = new CardRepository($this->pdo);
        $this->otpRepo = new PasswordResetOtpRepository($this->pdo);
        $this->tokenRepo = new PasswordResetTokenRepository($this->pdo);
        $this->auditRepo = new AuditEventRepository($this->pdo);

        $root = dirname(__DIR__);
        $this->mailLog = $root . '/storage/logs/mail.log';
        $this->auditLog = $root . '/storage/logs/audit.log';

        $this->authService = new AuthService($this->pdo, $this->userRepo, $this->cardRepo);
        $this->mailService = new MailService();
        $this->otpService = new PasswordResetOtpService(
            $this->pdo,
            $this->userRepo,
            $this->otpRepo,
            $this->tokenRepo,
            $this->mailService,
            $this->auditRepo
        );
        $this->authController = new AuthController(
            $this->authService,
            $this->otpService
        );
        $this->resetTestState();
    }

    private function resetTestState(): void
    {
        $_SESSION = [];
        $this->pdo->exec("DELETE FROM password_reset_otps");
        $this->pdo->exec("DELETE FROM password_reset_tokens");
    }

    private function createTestUser(string $prefix, string $password = 'Password123!'): array
    {
        $unique = bin2hex(random_bytes(4));
        $email = $prefix . '_' . $unique . '@scanv.test';

        $reg = $this->authService->register([
            'first_name' => 'Test',
            'last_name' => 'OTPUser',
            'login_email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'terms' => '1',
        ], '127.0.0.1');

        $user = $this->userRepo->findByEmail($email);
        $card = $this->cardRepo->findByUserId((int) $user->id);

        return [$user, $card];
    }

    /**
     * Extract the latest OTP code sent to an email from mail.log
     */
    private function getLatestOtpFromMailLog(string $email): ?string
    {
        if (!file_exists($this->mailLog)) {
            return null;
        }
        $content = (string) file_get_contents($this->mailLog);
        $entries = explode(str_repeat('=', 80), $content);
        for ($i = count($entries) - 1; $i >= 0; $i--) {
            $entry = $entries[$i];
            if (str_contains($entry, "<{$email}>")) {
                if (preg_match('/Ton code de vérification\s*:\s*([0-9]{6})/i', $entry, $m)) {
                    return $m[1];
                }
            }
        }
        return null;
    }

    /**
     * 1. Test complete valid OTP flow from request to password update.
     */
    public function testValidOtpWorkflowSuccess(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_success');

        // Step 1: Request OTP
        $ip = '10.1.1.1';
        $req = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $resp = $this->authController->forgot($req);

        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertEquals(url('/forgot/verify'), $resp->getHeaders()['Location']);
        $this->assertEquals($user->loginEmail, $_SESSION['forgot_email'] ?? null);

        // Fetch OTP from mail.log
        $otpCode = $this->getLatestOtpFromMailLog($user->loginEmail);
        $this->assertNotNull($otpCode, "OTP code must be logged in mail.log");
        $this->assertEquals(6, strlen($otpCode));

        // Step 2: Verify OTP
        $verifyReq = new Request('POST', '/forgot/verify', [], ['otp' => $otpCode], ['REMOTE_ADDR' => $ip]);
        $verifyResp = $this->authController->verifyOtp($verifyReq);

        $this->assertEquals(302, $verifyResp->getStatusCode());
        $this->assertEquals(url('/forgot/new-password'), $verifyResp->getHeaders()['Location']);
        $this->assertNotEmpty($_SESSION['password_reset_token'] ?? null, "Reset token must be present in session");

        // Step 3: Set new password
        $newPass = 'NewSecretPassword999!';
        $passReq = new Request('POST', '/forgot/new-password', [], [
            'password' => $newPass,
            'password_confirmation' => $newPass,
        ], ['REMOTE_ADDR' => $ip]);
        $passResp = $this->authController->newPassword($passReq);

        $this->assertEquals(302, $passResp->getStatusCode());
        $this->assertEquals(url('/login'), $passResp->getHeaders()['Location']);

        // Verify old password fails
        $loginOld = $this->authService->login($user->loginEmail, 'Password123!', $ip);
        $this->assertFalse($loginOld['success'], "Old password must fail");

        // Verify new password succeeds
        $loginNew = $this->authService->login($user->loginEmail, $newPass, $ip);
        $this->assertTrue($loginNew['success'], "New password must succeed");
    }

    /**
     * 2. Test 5 wrong attempts burns the OTP code.
     */
    public function testFiveWrongAttemptsBurnsOtp(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_burn');
        $ip = '10.1.1.2';

        $req = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $this->authController->forgot($req);

        $correctOtp = $this->getLatestOtpFromMailLog($user->loginEmail);

        // 4 wrong attempts
        for ($i = 1; $i <= 4; $i++) {
            $verifyReq = new Request('POST', '/forgot/verify', [], ['otp' => '000000'], ['REMOTE_ADDR' => $ip]);
            $resp = $this->authController->verifyOtp($verifyReq);
            $this->assertEquals(422, $resp->getStatusCode());
            $this->assertStringContains('reste', $resp->getContent());
        }

        // 5th wrong attempt -> code burned
        $verifyReq5 = new Request('POST', '/forgot/verify', [], ['otp' => '000000'], ['REMOTE_ADDR' => $ip]);
        $resp5 = $this->authController->verifyOtp($verifyReq5);
        $this->assertEquals(422, $resp5->getStatusCode());
        $this->assertStringContains('tentatives', $resp5->getContent());

        // Now even if user tries the CORRECT code, it is rejected because code was burned
        $verifyReqCorrect = new Request('POST', '/forgot/verify', [], ['otp' => $correctOtp], ['REMOTE_ADDR' => $ip]);
        $respCorrect = $this->authController->verifyOtp($verifyReqCorrect);
        $this->assertEquals(422, $respCorrect->getStatusCode());
        $this->assertStringContains('Aucun code actif', $respCorrect->getContent());
    }

    /**
     * 3. Test expired code is refused.
     */
    public function testExpiredCodeIsRefused(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_expired');
        $ip = '10.1.1.3';

        // Generate expired OTP directly via repo
        $code = '123456';
        $appKey = $_ENV['APP_KEY'] ?? '94c8de5b1a3e8729f041b6cd385e02419a738fc9841b80c57193f45610bcde82';
        $codeHash = hash_hmac('sha256', $user->id . ':' . $code, $appKey);
        $pastDate = gmdate('Y-m-d H:i:s', time() - 600); // 10 min ago

        $this->otpRepo->create((int) $user->id, $codeHash, $pastDate, $ip);

        $_SESSION['forgot_email'] = $user->loginEmail;
        $_SESSION['otp_email'] = $user->loginEmail;

        $verifyReq = new Request('POST', '/forgot/verify', [], ['otp' => $code], ['REMOTE_ADDR' => $ip]);
        $resp = $this->authController->verifyOtp($verifyReq);

        $this->assertEquals(422, $resp->getStatusCode());
        $this->assertStringContains('expiré', $resp->getContent());
    }

    /**
     * 4. Test consumed (reused) code is refused.
     */
    public function testReusedCodeIsRefused(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_reused');
        $ip = '10.1.1.4';

        $req = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $this->authController->forgot($req);
        $code = $this->getLatestOtpFromMailLog($user->loginEmail);

        // First verification -> Success
        $verifyReq1 = new Request('POST', '/forgot/verify', [], ['otp' => $code], ['REMOTE_ADDR' => $ip]);
        $resp1 = $this->authController->verifyOtp($verifyReq1);
        $this->assertEquals(302, $resp1->getStatusCode());

        // Second verification of SAME code -> Rejected (consumed)
        $_SESSION['forgot_email'] = $user->loginEmail;
        $_SESSION['otp_email'] = $user->loginEmail;
        $verifyReq2 = new Request('POST', '/forgot/verify', [], ['otp' => $code], ['REMOTE_ADDR' => $ip]);
        $resp2 = $this->authController->verifyOtp($verifyReq2);
        $this->assertEquals(422, $resp2->getStatusCode());
        $this->assertStringContains('Aucun code actif', $resp2->getContent());
    }

    /**
     * 5. Test previous code is invalidated when a new code is requested (resend).
     */
    public function testOldCodeRefusedAfterResend(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_resend_burn');
        $ip = '10.1.1.5';

        // 1st code
        $req1 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $this->authController->forgot($req1);
        $code1 = $this->getLatestOtpFromMailLog($user->loginEmail);

        // Force time past resend seconds in DB so resend is allowed
        $pastDate = gmdate('Y-m-d H:i:s', time() - 75);
        $this->pdo->prepare("UPDATE password_reset_otps SET sent_at = :p1, created_at = :p2 WHERE user_id = :uid")
            ->execute([':p1' => $pastDate, ':p2' => $pastDate, ':uid' => (int) $user->id]);
        $_SESSION['forgot_last_sent_at'] = time() - 75;

        // Request 2nd code via resend
        $resendReq = new Request('POST', '/forgot/resend', [], [], ['REMOTE_ADDR' => $ip]);
        $resendResp = $this->authController->resendOtp($resendReq);
        $this->assertEquals(302, $resendResp->getStatusCode());
        $code2 = $this->getLatestOtpFromMailLog($user->loginEmail);

        $this->assertTrue($code1 !== null && $code2 !== null, "Both codes must exist");

        // Attempt using code1 -> MUST BE REJECTED
        $verifyReq1 = new Request('POST', '/forgot/verify', [], ['otp' => $code1], ['REMOTE_ADDR' => $ip]);
        $respCode1 = $this->authController->verifyOtp($verifyReq1);
        $this->assertEquals(422, $respCode1->getStatusCode(), "Old code must be rejected");

        // Attempt using code2 -> MUST SUCCEED
        $verifyReq2 = new Request('POST', '/forgot/verify', [], ['otp' => $code2], ['REMOTE_ADDR' => $ip]);
        $respCode2 = $this->authController->verifyOtp($verifyReq2);
        $this->assertEquals(302, $respCode2->getStatusCode(), "New code must succeed");
    }

    /**
     * 6. Unknown and suspended email returns identical response and HTTP status.
     */
    public function testUnknownAndSuspendedEmailIdenticalResponse(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_unknown_test');
        // Suspend user (status column in users table)
        $this->pdo->exec("UPDATE users SET status = 'suspended' WHERE id = " . (int) $user->id);

        $ip = '10.1.1.6';

        // Suspended user
        $reqSuspended = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $respSuspended = $this->authController->forgot($reqSuspended);
        $this->assertEquals(302, $respSuspended->getStatusCode());
        $this->assertEquals(url('/forgot/verify'), $respSuspended->getHeaders()['Location']);

        // Unknown email
        $unknownEmail = 'completely_unknown_' . bin2hex(random_bytes(4)) . '@domain.test';
        $reqUnknown = new Request('POST', '/forgot', [], ['login_email' => $unknownEmail], ['REMOTE_ADDR' => $ip]);
        $respUnknown = $this->authController->forgot($reqUnknown);
        $this->assertEquals(302, $respUnknown->getStatusCode());
        $this->assertEquals(url('/forgot/verify'), $respUnknown->getHeaders()['Location']);
    }

    /**
     * 7. Rate limiting: 4th request in 1 hour returns HTTP 429.
     */
    public function testRateLimitingFourRequestsInOneHourBlocked(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_ratelimit');
        $ip = '10.1.1.7';

        // Send 1
        $r1 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $resp1 = $this->authController->forgot($r1);
        $this->assertEquals(302, $resp1->getStatusCode());

        // Fast forward 61 seconds so cooldown passes
        $past = gmdate('Y-m-d H:i:s', time() - 65);
        $this->pdo->prepare("UPDATE password_reset_otps SET sent_at = :p WHERE user_id = :uid")
            ->execute([':p' => $past, ':uid' => (int) $user->id]);

        // Send 2
        $r2 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $resp2 = $this->authController->forgot($r2);
        $this->assertEquals(302, $resp2->getStatusCode());

        // Fast forward 61 seconds
        $past = gmdate('Y-m-d H:i:s', time() - 65);
        $this->pdo->prepare("UPDATE password_reset_otps SET sent_at = :p WHERE user_id = :uid")
            ->execute([':p' => $past, ':uid' => (int) $user->id]);

        // Send 3
        $r3 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $resp3 = $this->authController->forgot($r3);
        $this->assertEquals(302, $resp3->getStatusCode());

        // Fast forward 61 seconds
        $past = gmdate('Y-m-d H:i:s', time() - 65);
        $this->pdo->prepare("UPDATE password_reset_otps SET sent_at = :p WHERE user_id = :uid")
            ->execute([':p' => $past, ':uid' => (int) $user->id]);

        // Send 4 -> MUST BE BLOCKED WITH HTTP 429
        $r4 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $resp4 = $this->authController->forgot($r4);
        $this->assertEquals(429, $resp4->getStatusCode(), "4th request within 1 hour must be HTTP 429");
        $this->assertStringContains('Trop de demandes', $resp4->getContent());
    }

    /**
     * 8. Resend cooldown before 60 seconds is refused with HTTP 429.
     */
    public function testResendBeforeCooldownRefused(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_cooldown');
        $ip = '10.1.1.8';

        $r1 = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $this->authController->forgot($r1);

        // Immediate resend (< 60s)
        $resendReq = new Request('POST', '/forgot/resend', [], [], ['REMOTE_ADDR' => $ip]);
        $resp = $this->authController->resendOtp($resendReq);

        $this->assertEquals(429, $resp->getStatusCode(), "Resending before 60s cooldown must return HTTP 429");
    }

    /**
     * 9. Direct access to /forgot/new-password without valid intermediate token is refused.
     */
    public function testDirectAccessToNewPasswordWithoutTokenRefused(): void
    {
        $this->resetTestState();
        unset($_SESSION['password_reset_token'], $_SESSION['password_reset_user_id'], $_SESSION['reset_token'], $_SESSION['reset_user_id']);

        $req = new Request('GET', '/forgot/new-password');
        $resp = $this->authController->showNewPassword($req);

        $this->assertEquals(302, $resp->getStatusCode());
        $this->assertEquals(url('/forgot'), $resp->getHeaders()['Location']);
    }

    /**
     * 10. Password reset invalidates other user sessions (session password stamp check).
     */
    public function testPasswordResetInvalidatesOtherActiveSessions(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_killsess', 'Pass12345678!');
        $ip = '10.1.1.9';

        // Simulate active session for this user
        $this->authService->startUserSession($user);
        $currentUser = $this->authService->getCurrentUser();
        $this->assertNotNull($currentUser, "User should be logged in");

        // Now simulate password reset for this user
        $token = bin2hex(random_bytes(32));
        $this->tokenRepo->create((int) $user->id, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 600));

        $_SESSION['password_reset_token'] = $token;
        $_SESSION['password_reset_user_id'] = (int) $user->id;

        $newPass = 'NewPass987654321!';
        $postReq = new Request('POST', '/forgot/new-password', [], [
            'password' => $newPass,
            'password_confirmation' => $newPass,
        ], ['REMOTE_ADDR' => $ip]);
        $resp = $this->authController->newPassword($postReq);
        $this->assertEquals(302, $resp->getStatusCode());

        // Previous session check: password stamp no longer matches new password hash in DB
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['user_password_stamp'] = hash('sha256', (string) $user->passwordHash); // old stamp!

        $refetchedUser = $this->authService->getCurrentUser();
        $this->assertNull($refetchedUser, "Other active sessions with old password stamp must be immediately terminated");
    }

    /**
     * 11. Code and password never appear in audit logs.
     */
    public function testAuditLogsNeverContainRawCodeOrPassword(): void
    {
        $this->resetTestState();
        [$user, $card] = $this->createTestUser('otp_audit_sec');
        $ip = '10.1.1.10';

        $req = new Request('POST', '/forgot', [], ['login_email' => $user->loginEmail], ['REMOTE_ADDR' => $ip]);
        $this->authController->forgot($req);
        $code = $this->getLatestOtpFromMailLog($user->loginEmail);

        // Check audit table in database
        $stmt = $this->pdo->prepare("SELECT * FROM audit_events WHERE user_id = :uid");
        $stmt->execute(['uid' => (int) $user->id]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $details = (string) ($row['details'] ?? '');
            if ($code) {
                $this->assertStringNotContains($code, $details, "Audit log details must never contain OTP code");
            }
            $this->assertStringNotContains($user->loginEmail, $details, "Audit log must not contain unmasked email");
        }
    }
}
