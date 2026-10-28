<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Service for sending system emails via PHPMailer / SMTP (F-19).
 * In local environment (or if SMTP is unavailable), logs email to storage/logs/mail.log.
 */
class MailerService
{
    private string $env;
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private string $logPath;

    public function __construct(
        ?string $env = null,
        ?string $host = null,
        ?int $port = null,
        ?string $username = null,
        ?string $password = null,
        ?string $encryption = null,
        ?string $fromAddress = null,
        ?string $fromName = null,
        ?string $logPath = null
    ) {
        $this->env = $env ?? (string) Env::get('APP_ENV', 'local');
        $this->host = $host ?? (string) Env::get('SMTP_HOST', '127.0.0.1');
        $this->port = $port ?? (int) Env::get('SMTP_PORT', 1025);
        $this->username = $username ?? (string) Env::get('SMTP_USERNAME', '');
        $this->password = $password ?? (string) Env::get('SMTP_PASSWORD', '');
        $this->encryption = $encryption ?? (string) Env::get('SMTP_ENCRYPTION', '');
        $this->fromAddress = $fromAddress ?? (string) Env::get('MAIL_FROM_ADDRESS', 'noreply@scanv.test');
        $this->fromName = $fromName ?? (string) Env::get('MAIL_FROM_NAME', 'ScanV');

        $root = dirname(__DIR__, 2);
        $this->logPath = $logPath ?? ($root . '/storage/logs/mail.log');
    }

    /**
     * Send password reset email containing single-use secure link (1 hour expiration).
     */
    public function sendPasswordResetEmail(string $recipientEmail, string $recipientName, string $resetUrl): bool
    {
        $subject = 'Réinitialisation de votre mot de passe ScanV';

        $htmlBody = '
            <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #1c1b1f;">
                <h2 style="color: #6750a4;">ScanV - Carte de visite digitale</h2>
                <p>Bonjour ' . htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8') . ',</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte ScanV.</p>
                <p>Cliquez sur le lien sécurisé ci-dessous pour choisir un nouveau mot de passe (valable pendant 1 heure et à usage unique) :</p>
                <p style="margin: 25px 0;">
                    <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '"
                       style="background: #6750a4; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;">
                        Réinitialiser mon mot de passe
                    </a>
                </p>
                <p style="font-size: 13px; color: #49454f;">Si le bouton ne fonctionne pas, copiez-collez l\'adresse suivante dans votre navigateur :<br>' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</p>
                <p style="font-size: 13px; color: #79747e; margin-top: 30px;">Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet e-mail. Votre mot de passe reste inchangé.</p>
            </div>
        ';

        $textBody = "Bonjour {$recipientName},\n\n"
            . "Vous avez demandé la réinitialisation de votre mot de passe pour votre compte ScanV.\n"
            . "Pour choisir un nouveau mot de passe (valable 1 heure, usage unique), rendez-vous sur le lien suivant :\n"
            . "{$resetUrl}\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.";

        // Always log in local environment
        if ($this->env === 'local') {
            $this->logEmailLocally($recipientEmail, $subject, $resetUrl);
            return true;
        }

        // Production / non-local SMTP delivery via PHPMailer
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = !empty($this->username);
            if ($mail->SMTPAuth) {
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            }

            if (strtolower($this->encryption) === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (strtolower($this->encryption) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($recipientEmail, $recipientName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            // Fallback to local log on SMTP failure
            $this->logEmailLocally($recipientEmail, $subject, $resetUrl, $e->getMessage());
            return false;
        }
    }

    /**
     * Log email and reset link to storage/logs/mail.log.
     */
    private function logEmailLocally(string $recipientEmail, string $subject, string $resetUrl, ?string $error = null): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $logEntry = sprintf(
            "[%s] TO: %s | SUBJECT: %s | RESET_URL: %s%s\n",
            gmdate('Y-m-d H:i:s'),
            $recipientEmail,
            $subject,
            $resetUrl,
            $error !== null ? " | ERROR: {$error}" : ''
        );

        @file_put_contents($this->logPath, $logEntry, FILE_APPEND);
    }
}
