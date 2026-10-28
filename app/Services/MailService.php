<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

/**
 * Service managing transactional emails via PHPMailer.
 * Drivers supported:
 *  - 'log'  : Writes full emails to storage/logs/mail.log (strictly forbidden if APP_ENV=production).
 *  - 'smtp' : Real SMTP delivery (Mailpit locally, production SMTP provider in prod).
 */
class MailService
{
    private string $driver;
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
        ?string $driver = null,
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
        $this->driver = strtolower($driver ?? (string) Env::get('MAIL_DRIVER', 'log'));
        $this->env = strtolower($env ?? (string) Env::get('APP_ENV', 'local'));
        $this->host = $host ?? (string) Env::get('MAIL_HOST', Env::get('SMTP_HOST', '127.0.0.1'));
        $this->port = $port ?? (int) Env::get('MAIL_PORT', Env::get('SMTP_PORT', 1025));
        $this->username = $username ?? (string) Env::get('MAIL_USERNAME', Env::get('SMTP_USERNAME', ''));
        $this->password = $password ?? (string) Env::get('MAIL_PASSWORD', Env::get('SMTP_PASSWORD', ''));
        $this->encryption = strtolower($encryption ?? (string) Env::get('MAIL_ENCRYPTION', Env::get('SMTP_ENCRYPTION', '')));
        $this->fromAddress = $fromAddress ?? (string) Env::get('MAIL_FROM_ADDRESS', 'noreply@scanv.test');
        $this->fromName = $fromName ?? (string) Env::get('MAIL_FROM_NAME', 'ScanV');

        $root = dirname(__DIR__, 2);
        $this->logPath = $logPath ?? ($root . '/storage/logs/mail.log');

        // Security check: Refuse to start in production with log driver
        if ($this->driver === 'log' && $this->env === 'production') {
            throw new RuntimeException("Le driver mail 'log' est strictement interdit en production. Configurez MAIL_DRIVER=smtp dans votre .env.");
        }
    }

    /**
     * Send 6-digit OTP verification email for password reset.
     * Note: Subject does NOT contain the OTP code.
     */
    public function sendOtpEmail(string $recipientEmail, string $recipientName, string $otp, int $ttlMinutes = 10): bool
    {
        $subject = 'Votre code de vérification ScanV';
        $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0F172A;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05); overflow: hidden;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background-color: #4338CA; padding: 24px 32px; text-align: left;">
                            <span style="font-size: 22px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.5px; font-family: sans-serif;">Scan<span style="color: #A5B4FC;">V</span></span>
                            <span style="display: block; font-size: 12px; color: #C7D2FE; margin-top: 4px;">Carte de visite numérique sécurisée</span>
                        </td>
                    </tr>
                    <!-- Main Body -->
                    <tr>
                        <td style="padding: 32px 32px 24px 32px; font-size: 15px; line-height: 1.6; color: #334155;">
                            <p style="margin-top: 0; margin-bottom: 16px; font-weight: 600; color: #0F172A; font-size: 16px;">Bonjour {$safeName},</p>
                            <p style="margin-top: 0; margin-bottom: 20px;">Tu as demandé la réinitialisation de ton mot de passe pour ton compte ScanV.</p>
                            
                            <!-- OTP Box -->
                            <div style="background-color: #EEF2FF; border: 2px dashed #818CF8; border-radius: 10px; padding: 20px; text-align: center; margin: 28px 0;">
                                <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #6366F1; margin-bottom: 8px;">Ton code de vérification</div>
                                <div style="font-family: 'Courier New', Courier, monospace; font-size: 38px; font-weight: 800; letter-spacing: 12px; color: #4338CA; margin: 6px 0; user-select: all;">{$safeOtp}</div>
                                <div style="font-size: 13px; color: #64748B; margin-top: 8px;">Il expire dans <strong>{$ttlMinutes} minutes</strong>.</div>
                            </div>

                            <p style="margin-top: 0; margin-bottom: 12px; font-size: 14px; color: #64748B;">Si tu n'es pas à l'origine de cette demande, ignore simplement ce message. Ton mot de passe actuel reste inchangé et aucune modification n'a été effectuée.</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 20px 32px; font-size: 12px; color: #94A3B8; text-align: center;">
                            Cet e-mail automatique est envoyé par ScanV. Pour toute question de sécurité, contacte notre équipe.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        $textBody = "Bonjour {$recipientName},\n\n"
            . "Tu as demandé la réinitialisation de ton mot de passe pour ton compte ScanV.\n\n"
            . "Ton code de vérification : {$otp}\n"
            . "Il expire dans {$ttlMinutes} minutes.\n\n"
            . "Si tu n'es pas à l'origine de cette demande, ignore ce message.\n\n"
            . "L'équipe ScanV";

        return $this->deliver($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
    }

    /**
     * Send password changed confirmation notification.
     */
    public function sendPasswordChangedNotification(string $recipientEmail, string $recipientName): bool
    {
        $subject = 'Votre mot de passe ScanV a été modifié';
        $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{$subject}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0F172A;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; padding: 40px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05); overflow: hidden;">
                    <tr>
                        <td style="background-color: #4338CA; padding: 24px 32px;">
                            <span style="font-size: 22px; font-weight: 800; color: #FFFFFF;">Scan<span style="color: #A5B4FC;">V</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px; font-size: 15px; line-height: 1.6; color: #334155;">
                            <p style="margin-top: 0; font-weight: 600; color: #0F172A; font-size: 16px;">Bonjour {$safeName},</p>
                            <p>Ton mot de passe a été modifié avec succès.</p>
                            <p style="background-color: #FEF2F2; border-left: 4px solid #EF4444; padding: 12px 16px; color: #991B1B; font-size: 14px; margin: 20px 0;">
                                <strong>Important :</strong> Si ce n'est pas toi qui as effectué cette modification, contacte-nous immédiatement pour sécuriser ton compte.
                            </p>
                            <p style="font-size: 13px; color: #64748B;">Par mesure de précaution, toutes tes autres sessions actives ont été déconnectées.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 16px 32px; font-size: 12px; color: #94A3B8; text-align: center;">
                            ScanV - Sécurité du compte
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        $textBody = "Bonjour {$recipientName},\n\n"
            . "Ton mot de passe a été modifié.\n"
            . "Si ce n'est pas toi, contacte-nous immédiatement.\n\n"
            . "L'équipe ScanV";

        return $this->deliver($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
    }

    /**
     * Dispatch email via configured driver (log or smtp).
     */
    private function deliver(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        if ($this->driver === 'log') {
            return $this->logDelivery($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
        }

        return $this->smtpDelivery($recipientEmail, $recipientName, $subject, $htmlBody, $textBody);
    }

    /**
     * Delivery via storage/logs/mail.log.
     */
    private function logDelivery(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $now = gmdate('Y-m-d H:i:s') . ' UTC';
        $divider = str_repeat('=', 80);
        $entry = <<<LOG
{$divider}
[{$now}] SCANV MAIL DISPATCH (Driver: log | Env: {$this->env})
To: "{$recipientName}" <{$recipientEmail}>
From: "{$this->fromName}" <{$this->fromAddress}>
Subject: {$subject}

--- TEXT VERSION ---
{$textBody}

--- HTML PREVIEW ---
{$htmlBody}

LOG;

        return @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Real delivery via PHPMailer SMTP.
     */
    private function smtpDelivery(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
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

            if ($this->encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($this->encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->Timeout = 10;
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($recipientEmail, $recipientName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            return $mail->send();
        } catch (PHPMailerException | Throwable $e) {
            // Log technical error server-side without revealing details to users
            @error_log("[ScanV MailService SMTP Error] Destination: {$recipientEmail} - Exception: " . $e->getMessage());
            return false;
        }
    }
}
