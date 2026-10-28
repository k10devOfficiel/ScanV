<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Models\User;
use App\Repositories\AuditEventRepository;
use App\Repositories\PasswordResetOtpRepository;
use App\Repositories\PasswordResetTokenRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Service managing the modern and secure 6-digit OTP password reset workflow.
 *
 * Security properties:
 *  - 6-digit random_int OTP.
 *  - Stored as HMAC-SHA256 (APP_KEY) bound to user ID.
 *  - hash_equals constant-time comparison.
 *  - 10-minute TTL (OTP_TTL_MINUTES). Single-use.
 *  - Max 5 attempts per OTP code, then burned.
 *  - 60-second cooldown between resends.
 *  - Max 3 requests per hour per email AND per IP. Over 3: HTTP 429 neutral message.
 *  - Cryptographic 32-byte reset token bridging verification (step 2) and new password (step 3).
 *  - Suspended accounts receive identical timing-equalized response without sending email.
 *  - Audit logging in audit_events with masked email (a***@domaine.com).
 */
class PasswordResetOtpService
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PasswordResetOtpRepository $otpRepo;
    private PasswordResetTokenRepository $tokenRepo;
    private MailService $mailService;
    private AuditEventRepository $auditRepo;
    private string $appKey;
    private int $ttlMinutes;
    private int $maxAttempts;
    private int $resendSeconds;

    public const MAX_SENDS_PER_HOUR = 3;

    public function __construct(
        ?PDO $pdo = null,
        ?UserRepository $userRepo = null,
        ?PasswordResetOtpRepository $otpRepo = null,
        ?PasswordResetTokenRepository $tokenRepo = null,
        ?MailService $mailService = null,
        ?AuditEventRepository $auditRepo = null,
        ?string $appKey = null,
        ?int $ttlMinutes = null,
        ?int $maxAttempts = null,
        ?int $resendSeconds = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->otpRepo = $otpRepo ?? new PasswordResetOtpRepository($this->pdo);
        $this->tokenRepo = $tokenRepo ?? new PasswordResetTokenRepository($this->pdo);
        $this->mailService = $mailService ?? new MailService();
        $this->auditRepo = $auditRepo ?? new AuditEventRepository($this->pdo);

        $this->appKey = $appKey ?? (string) Env::get('APP_KEY', '94c8de5b1a3e8729f041b6cd385e02419a738fc9841b80c57193f45610bcde82');
        $this->ttlMinutes = $ttlMinutes ?? (int) Env::get('OTP_TTL_MINUTES', 10);
        $this->maxAttempts = $maxAttempts ?? (int) Env::get('OTP_MAX_ATTEMPTS', 5);
        $this->resendSeconds = $resendSeconds ?? (int) Env::get('OTP_RESEND_SECONDS', 60);
    }

    /**
     * Step 1: Request a 6-digit OTP code.
     * Always returns neutral timing-resilient response to prevent user enumeration.
     *
     * @return array{success: bool, status: string, message: string, http_code: int, masked_email: string, retry_after?: int}
     */
    public function requestOtp(string $email, string $ip): array
    {
        $normalizedEmail = trim(strtolower($email));
        $maskedEmail = mask_email($normalizedEmail);
        $neutralSuccessMessage = "Si un compte existe, un code vient d'être envoyé.";

        // 1. IP-level rate limiting: max 3 requests per hour
        $ipSends = $this->otpRepo->countRecentSendsByIp($ip, 3600);
        if ($ipSends >= self::MAX_SENDS_PER_HOUR) {
            $this->auditRepo->log('otp_rate_limited_ip', null, null, $maskedEmail);
            return [
                'success' => false,
                'status' => 'rate_limited',
                'message' => 'Trop de demandes, réessaie plus tard.',
                'http_code' => 429,
                'masked_email' => $maskedEmail,
            ];
        }

        // 2. Email-level rate limiting: max 3 requests per hour
        $emailSends = $this->otpRepo->countRecentSendsByEmail($normalizedEmail, 3600);
        if ($emailSends >= self::MAX_SENDS_PER_HOUR) {
            $this->auditRepo->log('otp_rate_limited_email', null, null, $maskedEmail);
            return [
                'success' => false,
                'status' => 'rate_limited',
                'message' => 'Trop de demandes, réessaie plus tard.',
                'http_code' => 429,
                'masked_email' => $maskedEmail,
            ];
        }

        // Validate basic email format
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            usleep(random_int(20000, 40000));
            return [
                'success' => true,
                'status' => 'sent',
                'message' => $neutralSuccessMessage,
                'http_code' => 200,
                'masked_email' => $maskedEmail,
            ];
        }

        $user = $this->userRepo->findByEmail($normalizedEmail);

        // 3. User lookup & account status check
        if ($user === null || !$user->isActive()) {
            // Anti-enumeration: simulate work with random sleep to match real hash/DB/mail dispatch timing
            usleep(random_int(25000, 50000));
            $this->auditRepo->log('otp_requested_unknown_or_inactive', null, null, $maskedEmail);
            return [
                'success' => true,
                'status' => 'sent',
                'message' => $neutralSuccessMessage,
                'http_code' => 200,
                'masked_email' => $maskedEmail,
            ];
        }

        // 4. Cooldown check: resend allowed only after OTP_RESEND_SECONDS (60s)
        $latestSend = $this->otpRepo->getLatestSendTimeByUserId((int) $user->id);
        if ($latestSend !== null) {
            $elapsed = time() - $latestSend;
            if ($elapsed < $this->resendSeconds) {
                $retryAfter = $this->resendSeconds - $elapsed;
                return [
                    'success' => false,
                    'status' => 'too_soon',
                    'message' => "Veuillez patienter encore {$retryAfter} secondes avant de demander un nouveau code.",
                    'http_code' => 429,
                    'masked_email' => $maskedEmail,
                    'retry_after' => $retryAfter,
                ];
            }
        }

        // 5. Invalidate any existing OTPs for this user (single active code rule)
        $this->otpRepo->invalidateAllForUser((int) $user->id);

        // 6. Generate 6-digit random code
        $otp = (string) random_int(100000, 999999);

        // 7. HMAC-SHA256 bound to user ID (Rule: never stored in plain text)
        $codeHash = hash_hmac('sha256', $user->id . ':' . $otp, $this->appKey);

        // 8. Store OTP in database with TTL
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($this->ttlMinutes * 60));
        $this->otpRepo->create((int) $user->id, $codeHash, $expiresAt, $ip);

        // 9. Send email via configured MailService driver (log or smtp)
        $this->mailService->sendOtpEmail(
            $user->loginEmail,
            $user->getFullName(),
            $otp,
            $this->ttlMinutes
        );

        // 10. Audit logging without sensitive data
        $this->auditRepo->log('otp_requested', (int) $user->id, null, $maskedEmail);

        return [
            'success' => true,
            'status' => 'sent',
            'message' => $neutralSuccessMessage,
            'http_code' => 200,
            'masked_email' => $maskedEmail,
        ];
    }

    /**
     * Step 2: Verify the 6-digit OTP code.
     *
     * @return array{
     *     success: bool,
     *     error?: string,
     *     remaining_attempts?: int,
     *     max_attempts?: bool,
     *     expired?: bool,
     *     user?: User,
     *     reset_token?: string
     * }
     */
    public function verifyOtp(string $email, string $submittedOtp): array
    {
        $normalizedEmail = trim(strtolower($email));
        $cleanOtp = preg_replace('/\D/', '', trim($submittedOtp));
        $maskedEmail = mask_email($normalizedEmail);

        $user = $this->userRepo->findByEmail($normalizedEmail);
        if ($user === null || !$user->isActive()) {
            usleep(random_int(15000, 30000));
            return [
                'success' => false,
                'error' => 'Code incorrect ou expiré.',
                'remaining_attempts' => 0,
            ];
        }

        // Find latest active OTP
        $otpRecord = $this->otpRepo->findLatestActiveByUserId((int) $user->id);
        if ($otpRecord === null) {
            return [
                'success' => false,
                'error' => 'Aucun code actif. Veuillez demander un nouveau code.',
                'expired' => true,
            ];
        }

        // 1. Check expiration (10 minutes TTL)
        if (strtotime((string) $otpRecord['expires_at'] . ' UTC') < time()) {
            $this->otpRepo->markConsumed((int) $otpRecord['id']);
            $this->auditRepo->log('otp_expired', (int) $user->id, null, $maskedEmail);
            return [
                'success' => false,
                'error' => 'Code expiré. Veuillez redemander un nouveau code.',
                'expired' => true,
            ];
        }

        // 2. Check maximum attempts (max 5)
        $attempts = (int) $otpRecord['attempts'];
        if ($attempts >= $this->maxAttempts) {
            $this->otpRepo->markConsumed((int) $otpRecord['id']);
            $this->auditRepo->log('otp_max_attempts_exceeded', (int) $user->id, null, $maskedEmail);
            return [
                'success' => false,
                'error' => 'Code invalidé suite à 5 tentatives infructueuses. Veuillez en demander un nouveau.',
                'max_attempts' => true,
                'remaining_attempts' => 0,
            ];
        }

        // 3. Verify submitted OTP using constant-time hash_equals
        $expectedHash = hash_hmac('sha256', $user->id . ':' . $cleanOtp, $this->appKey);
        if (strlen($cleanOtp) !== 6 || !hash_equals((string) $otpRecord['code_hash'], $expectedHash)) {
            $newAttempts = $this->otpRepo->incrementAttempts((int) $otpRecord['id']);
            $remaining = max(0, $this->maxAttempts - $newAttempts);
            $this->auditRepo->log('otp_failed', (int) $user->id, null, $maskedEmail);

            if ($remaining <= 0) {
                $this->otpRepo->markConsumed((int) $otpRecord['id']);
                return [
                    'success' => false,
                    'error' => 'Code invalidé suite à 5 tentatives infructueuses. Veuillez en demander un nouveau.',
                    'max_attempts' => true,
                    'remaining_attempts' => 0,
                ];
            }

            return [
                'success' => false,
                'error' => "Code incorrect, il te reste {$remaining} essai" . ($remaining > 1 ? 's' : '') . '.',
                'remaining_attempts' => $remaining,
            ];
        }

        // 4. Success: Consume OTP immediately
        $this->otpRepo->markConsumed((int) $otpRecord['id']);
        $this->auditRepo->log('otp_verified', (int) $user->id, null, $maskedEmail);

        // 5. Invalidate any existing reset tokens for this user
        $this->tokenRepo->invalidateAllForUser((int) $user->id);

        // 6. Generate cryptographically secure 32-byte reset token bridging Step 2 & 3
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $tokenExpiresAt = gmdate('Y-m-d H:i:s', time() + ($this->ttlMinutes * 60));
        $this->tokenRepo->create((int) $user->id, $tokenHash, $tokenExpiresAt);

        return [
            'success' => true,
            'user' => $user,
            'reset_token' => $plainToken,
        ];
    }

    /**
     * Validate the intermediate 32-byte reset token (Step 3 guard).
     *
     * @return array{valid: bool, error?: string, record?: array<string, mixed>, user?: User}
     */
    public function validateResetToken(string $plainToken, ?int $expectedUserId = null): array
    {
        $plainToken = trim($plainToken);
        if (strlen($plainToken) !== 64 || !ctype_xdigit($plainToken)) {
            return [
                'valid' => false,
                'error' => 'Jeton de réinitialisation invalide.',
            ];
        }

        $tokenHash = hash('sha256', $plainToken);
        $record = $this->tokenRepo->findByTokenHash($tokenHash);

        if ($record === null || !empty($record['used_at'])) {
            return [
                'valid' => false,
                'error' => 'Ce lien de réinitialisation est invalide ou a déjà été utilisé.',
            ];
        }

        if (strtotime((string) $record['expires_at'] . ' UTC') < time()) {
            return [
                'valid' => false,
                'error' => 'Ce lien de réinitialisation a expiré.',
            ];
        }

        if ($expectedUserId !== null && (int) $record['user_id'] !== $expectedUserId) {
            return [
                'valid' => false,
                'error' => 'Ce jeton n\'appartient pas à l\'utilisateur en cours.',
            ];
        }

        $user = $this->userRepo->findById((int) $record['user_id']);
        if ($user === null || !$user->isActive()) {
            return [
                'valid' => false,
                'error' => 'Compte utilisateur indisponible.',
            ];
        }

        return [
            'valid' => true,
            'record' => $record,
            'user' => $user,
        ];
    }

    /**
     * Step 3: Complete password reset with new password.
     *
     * @return array{success: bool, errors: array<string, string>}
     */
    public function completePasswordReset(
        string $plainToken,
        int $userId,
        string $newPassword,
        string $passwordConfirmation
    ): array {
        $tokenValidation = $this->validateResetToken($plainToken, $userId);
        if (!$tokenValidation['valid']) {
            return [
                'success' => false,
                'errors' => ['token' => $tokenValidation['error'] ?? 'Jeton invalide.'],
            ];
        }

        /** @var User $user */
        $user = $tokenValidation['user'];
        $errors = [];

        // Validation rules: same as registration (minimum 10 characters)
        if (mb_strlen($newPassword) < 10) {
            $errors['password'] = 'Le mot de passe doit comporter au moins 10 caractères.';
        } elseif (mb_strlen($newPassword) > 255) {
            $errors['password'] = 'Le mot de passe est trop long.';
        }

        if ($newPassword !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        // 1. Update password using Argon2id/Bcrypt (Rule 4)
        $this->userRepo->updatePassword((int) $user->id, $newPassword);

        // 2. Mark reset token as used (single-use burned)
        $this->tokenRepo->markUsed((int) $tokenValidation['record']['id']);

        // 3. Invalidate ALL OTPs and ALL tokens for this user
        $this->otpRepo->invalidateAllForUser((int) $user->id);
        $this->tokenRepo->invalidateAllForUser((int) $user->id);

        // 4. Send email notification
        $this->mailService->sendPasswordChangedNotification(
            $user->loginEmail,
            $user->getFullName()
        );

        // 5. Audit event
        $this->auditRepo->log('password_reset_success', (int) $user->id, null, mask_email($user->loginEmail));

        return [
            'success' => true,
            'errors' => [],
        ];
    }

    /**
     * Get configured resend delay in seconds.
     */
    public function getResendSeconds(): int
    {
        return $this->resendSeconds;
    }
}
