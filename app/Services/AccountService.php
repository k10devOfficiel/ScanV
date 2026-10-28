<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\BusinessCard;
use App\Models\User;
use App\Repositories\AuditEventRepository;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use PDO;

/**
 * Service managing user account actions:
 * - Card active/inactive status toggle (F-17).
 * - Full account deletion with file purge and anonymized audit logging (F-18).
 */
class AccountService
{
    private PDO $pdo;
    private UserRepository $userRepo;
    private CardRepository $cardRepo;
    private CardImageService $imageService;
    private CardPdfService $pdfService;
    private AuditEventRepository $auditRepo;
    private string $uploadDir;
    private string $generatedDir;
    private string $auditLogPath;

    public function __construct(
        ?PDO $pdo = null,
        ?UserRepository $userRepo = null,
        ?CardRepository $cardRepo = null,
        ?CardImageService $imageService = null,
        ?CardPdfService $pdfService = null,
        ?AuditEventRepository $auditRepo = null,
        ?string $uploadDir = null,
        ?string $generatedDir = null,
        ?string $auditLogPath = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->cardRepo = $cardRepo ?? new CardRepository($this->pdo);
        $this->imageService = $imageService ?? new CardImageService($this->cardRepo);
        $this->pdfService = $pdfService ?? new CardPdfService($this->cardRepo);
        $this->auditRepo = $auditRepo ?? new AuditEventRepository($this->pdo);

        $root = dirname(__DIR__, 2);
        $this->uploadDir = $uploadDir ?? ($root . '/storage/private-uploads');
        $this->generatedDir = $generatedDir ?? ($root . '/storage/generated');
        $this->auditLogPath = $auditLogPath ?? ($root . '/storage/logs/audit.log');
    }

    /**
     * Toggle business card active state (F-17).
     *
     * @return array{success: bool, isActive: bool, message: string}
     */
    public function toggleCardActive(int $userId): array
    {
        $card = $this->cardRepo->findByUserId($userId);
        if ($card === null) {
            return [
                'success' => false,
                'isActive' => false,
                'message' => 'Aucune carte de visite associée à ce compte.',
            ];
        }

        $newState = !$card->isActive;
        $this->cardRepo->setIsActive((int) $card->id, $newState);

        // Invalidate cached exports so state change is reflected
        $this->imageService->invalidateCache((int) $card->id);
        $this->pdfService->invalidateCache((int) $card->id);

        // Anonymized audit log
        $this->auditRepo->log($newState ? 'card_activated' : 'card_deactivated', $userId, (int) $card->id);

        $message = $newState
            ? 'Votre carte est désormais active et accessible publiquement.'
            : 'Votre carte a été désactivée. Elle affiche désormais la page « Carte indisponible » sans aucune donnée personnelle.';

        return [
            'success' => true,
            'isActive' => $newState,
            'message' => $message,
        ];
    }

    /**
     * Delete user account completely with files purge and anonymized audit log (F-18).
     *
     * @return array{success: bool, error: ?string}
     */
    public function deleteAccount(int $userId, string $password): array
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            return [
                'success' => false,
                'error' => 'Utilisateur introuvable.',
            ];
        }

        // Verify password confirmation
        if (!$user->verifyPassword($password)) {
            return [
                'success' => false,
                'error' => 'Le mot de passe saisi est incorrect. Suppression annulée.',
            ];
        }

        $card = $this->cardRepo->findByUserId($userId);

        // 1. Purge uploaded files (photo and logo)
        if ($card !== null) {
            $this->purgeUploadedFiles($card);
            $this->purgeGeneratedExports((int) $card->id);
        }

        // 2. Delete user record in DB (cascades to business_cards, card_visibility, card_links, password_resets)
        $this->userRepo->delete($userId);

        // 3. Record anonymized audit events (without any personal data, Rule 14 & F-18)
        $this->auditRepo->log('account_deleted', null, null);
        $this->logAuditFileAnonymously('Account deleted successfully (anonymized audit).');

        return [
            'success' => true,
            'error' => null,
        ];
    }

    /**
     * Purge uploaded avatar and logo from disk.
     */
    private function purgeUploadedFiles(BusinessCard $card): void
    {
        if (!empty($card->photoPath)) {
            $photoFile = $this->uploadDir . '/' . basename($card->photoPath);
            if (file_exists($photoFile)) {
                @unlink($photoFile);
            }
        }

        if (!empty($card->logoPath)) {
            $logoFile = $this->uploadDir . '/' . basename($card->logoPath);
            if (file_exists($logoFile)) {
                @unlink($logoFile);
            }
        }
    }

    /**
     * Purge generated exports (PNG and PDF cache) for a given card ID.
     */
    private function purgeGeneratedExports(int $cardId): void
    {
        if (!is_dir($this->generatedDir)) {
            return;
        }

        $pattern = $this->generatedDir . "/card_{$cardId}_*";
        $files = glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Write an anonymized entry to storage/logs/audit.log without personal data.
     */
    private function logAuditFileAnonymously(string $message): void
    {
        $dir = dirname($this->auditLogPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $entry = sprintf("[%s] %s\n", gmdate('Y-m-d H:i:s'), $message);
        @file_put_contents($this->auditLogPath, $entry, FILE_APPEND);
    }
}
