<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Models\User;
use App\Repositories\AuditEventRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Service managing secure password reset flow (F-19).
 * - Random cryptographically secure tokens (random_bytes).
 * - Token stored hashed with sha256 (Rule 4 / F-19).
 * - Expiration 1 hour and single-use enforcement.
 * - Indistinguishable response whether email exists or not (anti-enumeration).
 */
class PasswordResetService
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private PasswordResetRepository $resetRepo;
    private MailerService $mailerService;
    private AuditEventRepository $auditRepo;
    private string $appUrl;

    public const TOKEN_EXPIRATION_SECONDS = 3600; // 1 hour

    public function __construct(
        ?PDO $pdo = null,
        ?UserRepository $userRepo = null,
        ?PasswordResetRepository $resetRepo = null,
        ?MailerService $mailerService = null,
        ?AuditEventRepository $auditRepo = null,
        ?string $appUrl = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->resetRepo = $resetRepo ?? new PasswordResetRepository($this->pdo);
        $this->mailerService = $mailerService ?? new MailerService();
        $this->auditRepo = $auditRepo ?? new AuditEventRepository($this->pdo);
        $this->appUrl = $appUrl ?? rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
    }

    /**
     * Request a password reset.
     * Always returns success to caller to prevent user enumeration.
     */
    public function requestReset(string $email): bool
    {
        $normalizedEmail = trim(strtolower($email));
        if ($normalizedEmail === '' || !filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            // Constant time dummy sleep / return true
            return true;
        }

        $user = $this->userRepo->findByEmail($normalizedEmail);
        if ($user === null || !$user->isActive()) {
            // Anti-enumeration: simulate work without revealing user absence
            usleep(random_int(10000, 30000));
            return true;
        }

        // 1. Invalidate previous pending tokens for this user
        $this->resetRepo->invalidateAllForUser((int) $user->id);

        // 2. Generate cryptographically secure 32-byte (64 hex chars) token
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        // 3. Store hashed token with 1 hour expiration
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::TOKEN_EXPIRATION_SECONDS);
        $this->resetRepo->create((int) $user->id, $tokenHash, $expiresAt);

        // 4. Build secure URL with plain token
        $resetUrl = $this->appUrl . '/reset-password?token=' . $plainToken;

        // 5. Send email (or log in storage/logs in local)
        $this->mailerService->sendPasswordResetEmail(
            recipientEmail: $user->loginEmail,
            recipientName: $user->getFullName(),
            resetUrl: $resetUrl
        );

        // 6. Audit event without personal data
        $this->auditRepo->log('password_reset_requested', (int) $user->id);

        return true;
    }

    /**
     * Validate reset token status.
     *
     * @return array{valid: bool, error: ?string, reset: ?array, user: ?User}
     */
    public function validateToken(string $plainToken): array
    {
        $plainToken = trim($plainToken);

        // Token must be exactly 64 hex characters
        if (strlen($plainToken) !== 64 || !ctype_xdigit($plainToken)) {
            return [
                'valid' => false,
                'error' => 'Ce lien de réinitialisation est invalide ou corrompu.',
                'reset' => null,
                'user' => null,
            ];
        }

        $tokenHash = hash('sha256', $plainToken);
        $reset = $this->resetRepo->findByTokenHash($tokenHash);

        if ($reset === null) {
            return [
                'valid' => false,
                'error' => 'Ce lien de réinitialisation est invalide ou a expiré.',
                'reset' => null,
                'user' => null,
            ];
        }

        // Check if token was already used (single-use enforcement)
        if ($reset['used_at'] !== null) {
            return [
                'valid' => false,
                'error' => 'Ce lien de réinitialisation a déjà été utilisé.',
                'reset' => $reset,
                'user' => null,
            ];
        }

        // Check expiration (expires_at is in UTC)
        $now = gmdate('Y-m-d H:i:s');
        if ($reset['expires_at'] < $now) {
            return [
                'valid' => false,
                'error' => 'Ce lien de réinitialisation a expiré (durée de validité : 1 heure).',
                'reset' => $reset,
                'user' => null,
            ];
        }

        $user = $this->userRepo->findById((int) $reset['user_id']);
        if ($user === null || !$user->isActive()) {
            return [
                'valid' => false,
                'error' => 'Le compte associé à ce lien est introuvable ou inactif.',
                'reset' => $reset,
                'user' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'reset' => $reset,
            'user' => $user,
        ];
    }

    /**
     * Complete password reset by changing password and burning token.
     *
     * @return array{success: bool, errors: array<string, string>}
     */
    public function resetPassword(string $plainToken, string $newPassword, string $passwordConfirm): array
    {
        $tokenStatus = $this->validateToken($plainToken);
        if (!$tokenStatus['valid']) {
            return [
                'success' => false,
                'errors' => ['token' => (string) $tokenStatus['error']],
            ];
        }

        $errors = [];

        // Password policy: minimum 10 characters (aligned with registration)
        if (mb_strlen($newPassword) < 10) {
            $errors['password'] = 'Le mot de passe doit comporter au moins 10 caractères.';
        } elseif ($newPassword !== $passwordConfirm) {
            $errors['password_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $reset = $tokenStatus['reset'];
        $user = $tokenStatus['user'];

        // 1. Update password with Argon2id / Bcrypt
        $this->userRepo->updatePassword((int) $user->id, $newPassword);

        // 2. Mark this token as used immediately (single-use)
        $this->resetRepo->markUsed((int) $reset['id']);

        // 3. Invalidate any other pending tokens for this user
        $this->resetRepo->invalidateAllForUser((int) $user->id);

        // 4. Log audit event without personal data
        $this->auditRepo->log('password_reset_completed', (int) $user->id);

        return [
            'success' => true,
            'errors' => [],
        ];
    }
}
