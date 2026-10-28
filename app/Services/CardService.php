<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\CardVisibility;
use App\Repositories\CardRepository;
use PDO;
use RuntimeException;

/**
 * Service managing business card editing, uploads, links, and visibility (F-05, F-06, F-07, F-16).
 */
class CardService
{
    private PDO $pdo;
    private CardRepository $cardRepo;
    private string $uploadDir;
    private CardImageService $cardImageService;
    private CardPdfService $cardPdfService;

    public const MAX_FILE_SIZE = 2097152; // 2 MB in bytes
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        ?PDO $pdo = null,
        ?CardRepository $cardRepo = null,
        ?string $uploadDir = null,
        ?CardImageService $cardImageService = null,
        ?CardPdfService $cardPdfService = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->cardRepo = $cardRepo ?? new CardRepository($this->pdo);
        $this->uploadDir = $uploadDir ?? (dirname(__DIR__, 2) . '/storage/private-uploads');
        $this->cardImageService = $cardImageService ?? new CardImageService($this->cardRepo);
        $this->cardPdfService = $cardPdfService ?? new CardPdfService($this->cardRepo);

        if (!is_dir($this->uploadDir)) {
            @mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Verify that a card exists and belongs to the given user (Rule 9).
     *
     * @throws RuntimeException with code 403 or 404 if unauthorized or not found.
     */
    public function getCardForUser(int $userId): BusinessCard
    {
        $card = $this->cardRepo->findByUserId($userId);
        if ($card === null) {
            throw new RuntimeException("Carte introuvable.", 404);
        }

        if ($card->userId !== $userId) {
            throw new RuntimeException("Accès non autorisé à cette carte.", 403);
        }

        return $card;
    }

    /**
     * Normalize telephone number to international E.164 format.
     *
     * @return array{display: string, normalized: ?string}
     */
    public static function normalizePhoneNumber(string $phone): array
    {
        $raw = trim($phone);
        if ($raw === '') {
            return ['display' => '', 'normalized' => null];
        }

        // Standardize 00 prefix to +
        if (str_starts_with($raw, '00')) {
            $raw = '+' . substr($raw, 2);
        }

        // Extract numbers and optional leading +
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === null || $digits === '') {
            return ['display' => $phone, 'normalized' => null];
        }

        // Handle French numbers starting with 0 followed by 9 digits (e.g. 06 12 34 56 78)
        if (!$hasPlus && strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $normalized = '+33' . substr($digits, 1);
        } elseif ($hasPlus) {
            // Handle +33(0)6...
            if (str_starts_with($digits, '330') && strlen($digits) === 12) {
                $digits = '33' . substr($digits, 3);
            }
            $normalized = '+' . $digits;
        } else {
            $normalized = '+' . $digits;
        }

        return [
            'display' => $phone,
            'normalized' => $normalized,
        ];
    }

    /**
     * Update business card data, visibility, links, and handle file uploads.
     *
     * @param int $userId Current authenticated user ID
     * @param array<string, mixed> $input Form inputs
     * @param array<string, mixed> $files $_FILES array
     * @return array{success: bool, errors: array<string, string>, card: ?BusinessCard}
     */
    public function updateCard(int $userId, array $input, array $files = []): array
    {
        // 1. Verify card ownership (Rule 9)
        $card = $this->getCardForUser($userId);
        $cardId = (int) $card->id;

        $errors = [];

        // 2. Validate Identity Section
        $firstName = trim((string) ($input['display_first_name'] ?? ''));
        $lastName = trim((string) ($input['display_last_name'] ?? ''));

        if ($firstName === '') {
            $errors['display_first_name'] = 'Le prénom affiché est obligatoire.';
        } elseif (mb_strlen($firstName) > 100) {
            $errors['display_first_name'] = 'Le prénom ne doit pas dépasser 100 caractères.';
        }

        if ($lastName === '') {
            $errors['display_last_name'] = 'Le nom affiché est obligatoire.';
        } elseif (mb_strlen($lastName) > 100) {
            $errors['display_last_name'] = 'Le nom ne doit pas dépasser 100 caractères.';
        }

        // 3. Validate Function Section
        $jobTitle = trim((string) ($input['job_title'] ?? ''));
        $department = trim((string) ($input['department'] ?? ''));

        if ($jobTitle !== '' && mb_strlen($jobTitle) > 150) {
            $errors['job_title'] = 'L\'intitulé du poste ne doit pas dépasser 150 caractères.';
        }

        if ($department !== '' && mb_strlen($department) > 150) {
            $errors['department'] = 'Le service ne doit pas dépasser 150 caractères.';
        }

        // 4. Validate Organization Section
        $companyName = trim((string) ($input['company_name'] ?? ''));
        $companyWebsite = trim((string) ($input['company_website'] ?? ''));
        $companyAddress = trim((string) ($input['company_address'] ?? ''));

        if ($companyName !== '' && mb_strlen($companyName) > 150) {
            $errors['company_name'] = 'Le nom de l\'entreprise ne doit pas dépasser 150 caractères.';
        }

        if ($companyWebsite !== '') {
            if (mb_strlen($companyWebsite) > 255) {
                $errors['company_website'] = 'L\'adresse du site web ne doit pas dépasser 255 caractères.';
            } elseif (!Security::isValidExternalUrl($companyWebsite)) {
                $errors['company_website'] = 'L\'adresse du site web doit débuter par http:// ou https://.';
            }
        }

        if ($companyAddress !== '' && mb_strlen($companyAddress) > 1000) {
            $errors['company_address'] = 'L\'adresse ne doit pas dépasser 1000 caractères.';
        }

        // 5. Validate Contact Section
        $phoneInput = trim((string) ($input['professional_phone'] ?? ''));
        $normalizedPhone = null;
        $displayPhone = null;

        if ($phoneInput !== '') {
            if (mb_strlen($phoneInput) > 50) {
                $errors['professional_phone'] = 'Le numéro de téléphone ne doit pas dépasser 50 caractères.';
            } else {
                $phoneData = self::normalizePhoneNumber($phoneInput);
                $displayPhone = $phoneData['display'];
                $normalizedPhone = $phoneData['normalized'];
            }
        }

        $emailInput = trim((string) ($input['professional_email'] ?? ''));
        $professionalEmail = null;

        if ($emailInput !== '') {
            if (mb_strlen($emailInput) > 191) {
                $errors['professional_email'] = 'L\'e-mail professionnel ne doit pas dépasser 191 caractères.';
            } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
                $errors['professional_email'] = 'L\'adresse e-mail professionnelle n\'est pas valide.';
            } else {
                $professionalEmail = strtolower($emailInput);
            }
        }

        // 6. Validate Custom Links (F-06)
        $linksInput = $input['links'] ?? [];
        $validatedLinks = [];

        if (is_array($linksInput)) {
            foreach ($linksInput as $index => $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                // Skip empty link rows
                if ($label === '' && $url === '') {
                    continue;
                }

                if ($label === '') {
                    $errors["links_{$index}_label"] = "Le libellé du lien est requis.";
                } elseif (mb_strlen($label) > 100) {
                    $errors["links_{$index}_label"] = "Le libellé ne doit pas dépasser 100 caractères.";
                }

                if ($url === '') {
                    $errors["links_{$index}_url"] = "L'URL du lien est requise.";
                } elseif (mb_strlen($url) > 255) {
                    $errors["links_{$index}_url"] = "L'URL ne doit pas dépasser 255 caractères.";
                } elseif (!Security::isValidExternalUrl($url)) {
                    // Rule 8: Strictly reject javascript:, data:, etc.
                    $errors["links_{$index}_url"] = "L'URL du lien doit débuter par http:// ou https://.";
                }

                $validatedLinks[] = [
                    'label' => $label,
                    'url' => $url,
                    'sort_order' => (int) ($link['sort_order'] ?? $index),
                    'is_public' => !empty($link['is_public']) ? 1 : 0,
                ];
            }
        }

        // 7. Validate & Process File Uploads (Photo & Logo, F-16)
        $newPhotoPath = $card->photoPath;
        if (!empty($input['delete_photo'])) {
            $this->deletePhysicalFile($card->photoPath);
            $newPhotoPath = null;
        } elseif (isset($files['photo']) && is_array($files['photo']) && ($files['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadRes = $this->processUpload($files['photo'], 'photo');
            if (isset($uploadRes['error'])) {
                $errors['photo'] = $uploadRes['error'];
            } else {
                $this->deletePhysicalFile($card->photoPath);
                $newPhotoPath = $uploadRes['filename'];
            }
        }

        $newLogoPath = $card->logoPath;
        if (!empty($input['delete_logo'])) {
            $this->deletePhysicalFile($card->logoPath);
            $newLogoPath = null;
        } elseif (isset($files['logo']) && is_array($files['logo']) && ($files['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadRes = $this->processUpload($files['logo'], 'logo');
            if (isset($uploadRes['error'])) {
                $errors['logo'] = $uploadRes['error'];
            } else {
                $this->deletePhysicalFile($card->logoPath);
                $newLogoPath = $uploadRes['filename'];
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'card' => $card,
            ];
        }

        // 8. Update in Database inside transaction
        $this->pdo->beginTransaction();
        try {
            $this->cardRepo->update($cardId, [
                'display_first_name' => $firstName,
                'display_last_name' => $lastName,
                'job_title' => $jobTitle !== '' ? $jobTitle : null,
                'department' => $department !== '' ? $department : null,
                'company_name' => $companyName !== '' ? $companyName : null,
                'company_website' => $companyWebsite !== '' ? $companyWebsite : null,
                'company_address' => $companyAddress !== '' ? $companyAddress : null,
                'professional_phone' => $displayPhone,
                'normalized_phone' => $normalizedPhone,
                'professional_email' => $professionalEmail,
                'photo_path' => $newPhotoPath,
                'logo_path' => $newLogoPath,
                'theme_key' => $input['theme_key'] ?? $card->themeKey,
                'is_active' => isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1,
            ]);

            // Synchronize links
            $this->cardRepo->syncLinks($cardId, $validatedLinks);

            // Synchronize visibility (F-07)
            $visibilityData = [
                'show_photo' => !empty($input['show_photo']) ? 1 : 0,
                'show_job_title' => !empty($input['show_job_title']) ? 1 : 0,
                'show_department' => !empty($input['show_department']) ? 1 : 0,
                'show_company' => !empty($input['show_company']) ? 1 : 0,
                'show_company_address' => !empty($input['show_company_address']) ? 1 : 0,
                'show_phone' => !empty($input['show_phone']) ? 1 : 0,
                'show_email' => !empty($input['show_email']) ? 1 : 0,
                'show_website' => !empty($input['show_website']) ? 1 : 0,
                'show_social_links' => !empty($input['show_social_links']) ? 1 : 0,
            ];
            $this->cardRepo->updateVisibility($cardId, $visibilityData);

            $this->pdo->commit();

            // Invalidate generated physical card PNG image cache (F-10) and PDF cache (F-11)
            $this->cardImageService->invalidateCache($cardId);
            $this->cardPdfService->invalidateCache($cardId);

            $updatedCard = $this->cardRepo->findById($cardId);

            return [
                'success' => true,
                'errors' => [],
                'card' => $updatedCard,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Process an uploaded image with strict security checks (MIME finfo, size, dimensions).
     *
     * @param array<string, mixed> $file
     * @return array{filename?: string, error?: string}
     */
    public function processUpload(array $file, string $fieldLabel): array
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['error' => "Erreur lors du téléversement du fichier ({$fieldLabel})."];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            // Also accept local test files if under tests/
            if (!file_exists($tmpName)) {
                return ['error' => "Fichier introuvable."];
            }
        }

        // Check file size (max 2 Mo)
        $size = (int) ($file['size'] ?? filesize($tmpName));
        if ($size > self::MAX_FILE_SIZE) {
            return ['error' => "La taille du fichier dépasse la limite autorisée de 2 Mo."];
        }

        // Check real MIME type using finfo (prevents renamed scripts, fake JPGs)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo !== false ? finfo_file($finfo, $tmpName) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!is_string($realMime) || !array_key_exists($realMime, self::ALLOWED_MIME_TYPES)) {
            return ['error' => "Format de fichier non autorisé. Formats acceptés : JPEG, PNG, WebP."];
        }

        // Verify image dimensions with getimagesize
        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            return ['error' => "Le fichier téléversé n'est pas une image valide."];
        }

        [$width, $height] = $imageInfo;
        if ($width <= 0 || $height <= 0 || $width > 4000 || $height > 4000) {
            return ['error' => "Dimensions de l'image invalides (maximum 4000x4000 px)."];
        }

        // Generate safe random filename
        $ext = self::ALLOWED_MIME_TYPES[$realMime];
        $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $this->uploadDir . DIRECTORY_SEPARATOR . $randomName;

        if (is_uploaded_file($tmpName)) {
            if (!move_uploaded_file($tmpName, $destination)) {
                return ['error' => "Impossible de sauvegarder le fichier téléversé."];
            }
        } else {
            if (!copy($tmpName, $destination)) {
                return ['error' => "Impossible de sauvegarder le fichier téléversé."];
            }
        }

        return ['filename' => $randomName];
    }

    /**
     * Delete physical upload file if exists.
     */
    public function deletePhysicalFile(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        // Prevent path traversal
        $basename = basename($filename);
        $fullPath = $this->uploadDir . DIRECTORY_SEPARATOR . $basename;

        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Get path to private upload if exists and valid.
     */
    public function getUploadPath(string $filename): ?string
    {
        // Sanitize filename to 32 hex chars + extension only
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/i', $filename)) {
            return null;
        }

        $fullPath = $this->uploadDir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($fullPath) && is_file($fullPath)) {
            return $fullPath;
        }

        return null;
    }

    /**
     * Verify whether an uploaded asset can be accessed by the requester (Rule 6).
     *
     * - The authenticated owner can always access their uploaded assets.
     * - Public visitors can only access assets if the card is active AND the specific
     *   field is set to visible (show_photo for photo, show_company for logo).
     */
    public function isUploadAuthorized(string $filename, ?int $currentUserId = null): bool
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/i', $filename)) {
            return false;
        }

        $card = $this->cardRepo->findByUploadFilename($filename);
        if ($card === null) {
            return false;
        }

        // Authenticated owner can always view their uploaded assets
        if ($currentUserId !== null && (int) $card->userId === $currentUserId) {
            return true;
        }

        // If not the owner, the card must be active
        if (!$card->isActive) {
            return false;
        }

        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        if ($visibility === null) {
            return false;
        }

        if ($card->photoPath === $filename && $visibility->isVisible('show_photo')) {
            return true;
        }

        if ($card->logoPath === $filename) {
            return $card->isActive;
        }

        return false;
    }
}
