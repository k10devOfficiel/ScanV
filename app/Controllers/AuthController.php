<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\PasswordResetOtpService;

/**
 * Controller handling user registration, authentication, logout, and password recovery (F-02, F-03, F-04, OTP Reset).
 */
class AuthController extends Controller
{
    private AuthService $authService;
    private PasswordResetOtpService $otpService;

    public function __construct(
        ?AuthService $authService = null,
        ?PasswordResetOtpService $otpService = null,
        mixed $resetService = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->otpService = $otpService ?? new PasswordResetOtpService();
    }

    /**
     * Show registration form (GET /register).
     */
    public function showRegister(Request $request): Response
    {
        return $this->render('auth/register', [
            'title' => 'Inscription - ScanV',
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Process registration submission (POST /register).
     */
    public function register(Request $request): Response
    {
        $input = [
            'first_name' => $request->post('first_name'),
            'last_name' => $request->post('last_name'),
            'login_email' => $request->post('login_email'),
            'password' => $request->post('password'),
            'password_confirmation' => $request->post('password_confirmation'),
            'terms' => $request->post('terms'),
        ];

        $result = $this->authService->register($input);

        if (!$result['success']) {
            return $this->render('auth/register', [
                'title' => 'Inscription - ScanV',
                'errors' => $result['errors'],
                'old' => [
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'login_email' => $input['login_email'],
                ],
            ], 'base', 422);
        }

        return Response::redirect(url('/dashboard'), 302);
    }

    /**
     * Show login form (GET /login).
     */
    public function showLogin(Request $request): Response
    {
        return $this->render('auth/login', [
            'title' => 'Connexion - ScanV',
            'error' => null,
            'flashSuccess' => flash('success'),
            'old' => ['login_email' => ''],
        ]);
    }

    /**
     * Process login submission (POST /login).
     */
    public function login(Request $request): Response
    {
        $email = (string) $request->post('login_email', '');
        $password = (string) $request->post('password', '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $result = $this->authService->login($email, $password, $ip);

        if (!$result['success']) {
            return $this->render('auth/login', [
                'title' => 'Connexion - ScanV',
                'error' => $result['error'],
                'flashSuccess' => null,
                'old' => ['login_email' => $email],
            ], 'base', 401);
        }

        return Response::redirect(url('/dashboard'), 302);
    }

    /**
     * Log out user (POST /logout).
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout();
        return Response::redirect(url('/login'), 302);
    }

    // =========================================================================
    // OTP PASSWORD RESET FLOW (3 STEPS)
    // =========================================================================

    /**
     * Step 1 (GET /forgot): Show email input form.
     */
    public function showForgot(Request $request): Response
    {
        return $this->render('auth/forgot', [
            'title' => 'Mot de passe oublié - ScanV',
            'oldEmail' => (string) ($_SESSION['forgot_email'] ?? ''),
            'error' => null,
        ]);
    }

    /**
     * Step 1 (POST /forgot): Request 6-digit OTP code.
     * Always returns identical response regardless of whether account exists or not (timing-equalized).
     */
    public function forgot(Request $request): Response
    {
        $email = (string) $request->post('login_email', '');
        $ip = $request->getIp();

        $result = $this->otpService->requestOtp($email, $ip);

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        if ($result['http_code'] === 429) {
            return $this->render('auth/forgot', [
                'title' => 'Mot de passe oublié - ScanV',
                'oldEmail' => $email,
                'error' => $result['message'],
            ], 'base', 429);
        }

        // Store non-sensitive verification flow metadata in session
        $normEmail = trim(strtolower($email));
        $_SESSION['forgot_email'] = $normEmail;
        $_SESSION['otp_email'] = $normEmail;
        $_SESSION['forgot_masked_email'] = $result['masked_email'];
        $_SESSION['forgot_last_sent_at'] = time();

        flash('info', $result['message']);
        return Response::redirect(url('/forgot/verify'), 302);
    }

    /**
     * Step 2 (GET /forgot/verify): Show 6-digit OTP code entry form.
     */
    public function showVerifyOtp(Request $request): Response
    {
        $email = $_SESSION['forgot_email'] ?? $_SESSION['otp_email'] ?? null;
        if (empty($email)) {
            flash('error', 'Veuillez saisir votre adresse e-mail pour recevoir un code.');
            return Response::redirect(url('/forgot'), 302);
        }

        $lastSent = (int) ($_SESSION['forgot_last_sent_at'] ?? time());
        $elapsed = time() - $lastSent;
        $resendCooldown = max(0, $this->otpService->getResendSeconds() - $elapsed);

        return $this->render('auth/verify', [
            'title' => 'Vérification du code - ScanV',
            'maskedEmail' => $_SESSION['forgot_masked_email'] ?? mask_email((string) $email),
            'resendCooldown' => $resendCooldown,
            'remainingSeconds' => $resendCooldown,
            'error' => null,
        ]);
    }

    /**
     * Step 2 (POST /forgot/verify): Verify submitted 6-digit OTP code.
     */
    public function verifyOtp(Request $request): Response
    {
        $email = $_SESSION['forgot_email'] ?? $_SESSION['otp_email'] ?? null;
        if (empty($email)) {
            return Response::redirect(url('/forgot'), 302);
        }

        // Support single input 'otp' or 'code', or split digit inputs
        $submittedCode = (string) ($request->post('otp') ?? $request->post('code') ?? '');
        if ($submittedCode === '') {
            $otpDigits = $request->post('otp_digits');
            if (is_array($otpDigits)) {
                $submittedCode = implode('', $otpDigits);
            } else {
                $digits = '';
                for ($i = 0; $i <= 6; $i++) {
                    $digits .= (string) ($request->post("digit_{$i}") ?? '');
                }
                $submittedCode = $digits;
            }
        }

        $result = $this->otpService->verifyOtp((string) $email, $submittedCode);

        if (!$result['success']) {
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                @session_regenerate_id(true);
            }

            $lastSent = (int) ($_SESSION['forgot_last_sent_at'] ?? time());
            $elapsed = time() - $lastSent;
            $resendCooldown = max(0, $this->otpService->getResendSeconds() - $elapsed);

            return $this->render('auth/verify', [
                'title' => 'Vérification du code - ScanV',
                'maskedEmail' => $_SESSION['forgot_masked_email'] ?? mask_email((string) $email),
                'resendCooldown' => $resendCooldown,
                'remainingSeconds' => $resendCooldown,
                'error' => $result['error'] ?? 'Code incorrect.',
            ], 'base', 422);
        }

        // OTP valid: generate intermediate 32-byte reset token and store in session
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        $_SESSION['password_reset_token'] = $result['reset_token'];
        $_SESSION['reset_token'] = $result['reset_token'];
        $_SESSION['password_reset_user_id'] = $result['user']->id;
        $_SESSION['reset_user_id'] = $result['user']->id;

        // Clean up step 1/2 state
        unset($_SESSION['forgot_email'], $_SESSION['otp_email'], $_SESSION['forgot_masked_email'], $_SESSION['forgot_last_sent_at']);

        return Response::redirect(url('/forgot/new-password'), 302);
    }

    /**
     * Step 2 Resend (POST /forgot/resend): Request code resend with 60s cooldown and 3/hr rate-limit.
     */
    public function resendOtp(Request $request): Response
    {
        $email = $_SESSION['forgot_email'] ?? $_SESSION['otp_email'] ?? null;
        if (empty($email)) {
            return Response::redirect(url('/forgot'), 302);
        }

        $result = $this->otpService->requestOtp((string) $email, $request->getIp());

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        if ($result['http_code'] === 429) {
            $resendCooldown = $result['retry_after'] ?? 60;
            return $this->render('auth/verify', [
                'title' => 'Vérification du code - ScanV',
                'maskedEmail' => $_SESSION['forgot_masked_email'] ?? mask_email((string) $email),
                'resendCooldown' => $resendCooldown,
                'error' => $result['message'],
            ], 'base', 429);
        }

        $_SESSION['forgot_last_sent_at'] = time();
        flash('info', 'Un nouveau code de vérification vient d\'être envoyé.');
        return Response::redirect(url('/forgot/verify'), 302);
    }

    /**
     * Step 3 (GET /forgot/new-password): Show new password form (guarded by verified token).
     */
    public function showNewPassword(Request $request): Response
    {
        $token = $_SESSION['password_reset_token'] ?? $_SESSION['reset_token'] ?? null;
        $userId = $_SESSION['password_reset_user_id'] ?? $_SESSION['reset_user_id'] ?? null;

        if (empty($token) || empty($userId)) {
            flash('error', 'Accès refusé. Veuillez d\'abord vérifier votre code à 6 chiffres.');
            return Response::redirect(url('/forgot'), 302);
        }

        $tokenValidation = $this->otpService->validateResetToken((string) $token, (int) $userId);
        if (!$tokenValidation['valid']) {
            unset($_SESSION['password_reset_token'], $_SESSION['password_reset_user_id'], $_SESSION['reset_token'], $_SESSION['reset_user_id']);
            flash('error', $tokenValidation['error'] ?? 'Votre session de réinitialisation a expiré.');
            return Response::redirect(url('/forgot'), 302);
        }

        return $this->render('auth/new-password', [
            'title' => 'Nouveau mot de passe - ScanV',
            'errors' => [],
        ]);
    }

    /**
     * Step 3 (POST /forgot/new-password): Complete password reset and invalidate other sessions.
     */
    public function newPassword(Request $request): Response
    {
        $token = $_SESSION['password_reset_token'] ?? $_SESSION['reset_token'] ?? null;
        $userId = $_SESSION['password_reset_user_id'] ?? $_SESSION['reset_user_id'] ?? null;

        if (empty($token) || empty($userId)) {
            flash('error', 'Accès refusé. Veuillez d\'abord vérifier votre code à 6 chiffres.');
            return Response::redirect(url('/forgot'), 302);
        }

        $password = (string) $request->post('password', '');
        $passwordConfirm = (string) $request->post('password_confirmation', '');

        $result = $this->otpService->completePasswordReset(
            (string) $token,
            (int) $userId,
            $password,
            $passwordConfirm
        );

        if (!$result['success']) {
            return $this->render('auth/new-password', [
                'title' => 'Nouveau mot de passe - ScanV',
                'errors' => $result['errors'],
            ], 'base', 422);
        }

        // Clean up reset token session state
        unset($_SESSION['password_reset_token'], $_SESSION['password_reset_user_id'], $_SESSION['reset_token'], $_SESSION['reset_user_id']);

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }

        flash('success', 'Ton mot de passe a été modifié avec succès. Tu peux maintenant te connecter.');
        return Response::redirect(url('/login'), 302);
    }

    // =========================================================================
    // BACKWARD COMPATIBILITY ALIASES
    // =========================================================================

    public function showForgotPassword(Request $request): Response
    {
        return $this->showForgot($request);
    }

    public function forgotPassword(Request $request): Response
    {
        return $this->forgot($request);
    }

    public function showResetPassword(Request $request): Response
    {
        return Response::redirect(url('/forgot'), 302);
    }

    public function resetPassword(Request $request): Response
    {
        return Response::redirect(url('/forgot'), 302);
    }
}
