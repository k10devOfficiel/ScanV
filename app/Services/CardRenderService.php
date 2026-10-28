<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\CardVisibility;
use App\Repositories\CardRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service producing HTML/CSS for physical business card recto/verso (F-08).
 * Target format: 85 × 55 mm (1004 × 650 px at 300 DPI).
 * Applies strict server-side visibility (Rule 6) and local fonts only.
 */
class CardRenderService
{
    private CardRepository $cardRepo;
    private QrCodeService $qrService;
    private string $viewsPath;
    private string $assetsPath;
    private string $uploadDir;

    public function __construct(
        ?CardRepository $cardRepo = null,
        ?QrCodeService $qrService = null,
        ?string $viewsPath = null,
        ?string $assetsPath = null,
        ?string $uploadDir = null
    ) {
        $pdo = Database::getConnection();
        $this->cardRepo = $cardRepo ?? new CardRepository($pdo);
        $this->qrService = $qrService ?? new QrCodeService();
        $root = dirname(__DIR__, 2);
        $this->viewsPath = $viewsPath ?? ($root . '/app/Views/card-templates');
        $this->assetsPath = $assetsPath ?? ($root . '/public/assets');
        $this->uploadDir = $uploadDir ?? ($root . '/storage/private-uploads');
    }

    /**
     * Render the front face (Recto) of a card.
     *
     * @param BusinessCard $card
     * @param CardVisibility|null $visibility If null, loads from database
     * @param bool $standalone If true, returns full HTML document (<!DOCTYPE html>...), else only card container
     * @param bool $inlineCss If true, embeds raw CSS inside <style> tags (ideal for PDF/PNG rendering)
     * @return string Rendered HTML
     */
    public function renderFront(
        BusinessCard $card,
        ?CardVisibility $visibility = null,
        bool $standalone = true,
        bool $inlineCss = false
    ): string {
        $theme = $this->resolveThemeKey($card->themeKey);
        $visibility = $visibility ?? $this->getCardVisibility($card);

        // Server-Side Visibility Enforcement (Rule 6)
        $displayName = $card->getDisplayName();

        $jobTitle = ($visibility->isVisible('show_job_title') && !empty(trim((string) $card->jobTitle)))
            ? trim((string) $card->jobTitle)
            : null;

        $department = ($visibility->isVisible('show_department') && !empty(trim((string) $card->department)))
            ? trim((string) $card->department)
            : null;

        $companyName = ($visibility->isVisible('show_company') && !empty(trim((string) $card->companyName)))
            ? trim((string) $card->companyName)
            : null;

        $companyWebsite = null;
        if ($visibility->isVisible('show_website') && !empty(trim((string) $card->companyWebsite))) {
            $rawWebsite = trim((string) $card->companyWebsite);
            if (Security::isValidExternalUrl($rawWebsite)) {
                $companyWebsite = $rawWebsite;
            }
        }

        $companyAddress = ($visibility->isVisible('show_company_address') && !empty(trim((string) $card->companyAddress)))
            ? trim((string) $card->companyAddress)
            : null;

        $phoneDisplay = ($visibility->isVisible('show_phone') && !empty(trim((string) $card->professionalPhone)))
            ? trim((string) $card->professionalPhone)
            : null;

        $email = ($visibility->isVisible('show_email') && !empty(trim((string) $card->professionalEmail)))
            ? trim((string) $card->professionalEmail)
            : null;

        // Resolve photo & logo (with base64 fallback for standalone/offline rendering)
        $photoUrl = null;
        if ($visibility->isVisible('show_photo') && !empty($card->photoPath)) {
            $photoUrl = $this->resolveMediaUrl($card->photoPath, $inlineCss);
        }

        $logoUrl = null;
        if (!empty($card->logoPath)) {
            $logoUrl = $this->resolveMediaUrl($card->logoPath, $inlineCss);
        }

        // Resolve public links
        $publicLinks = [];
        if ($visibility->isVisible('show_social_links')) {
            $rawLinks = $this->cardRepo->getLinks((int) $card->id, true);
            foreach ($rawLinks as $link) {
                $u = trim($link->url);
                $l = trim($link->label);
                if ($l !== '' && Security::isValidExternalUrl($u)) {
                    $publicLinks[] = [
                        'label' => $l,
                        'url' => $u,
                    ];
                }
            }
        }

        $cssContent = $inlineCss ? $this->getThemeCss($theme) : null;

        $data = [
            'card' => $card,
            'visibility' => $visibility,
            'displayName' => $displayName,
            'jobTitle' => $jobTitle,
            'department' => $department,
            'companyName' => $companyName,
            'companyWebsite' => $companyWebsite,
            'companyAddress' => $companyAddress,
            'phoneDisplay' => $phoneDisplay,
            'email' => $email,
            'photoUrl' => $photoUrl,
            'logoUrl' => $logoUrl,
            'links' => $publicLinks,
            'theme' => $theme,
            'standalone' => $standalone,
            'inlineCss' => $cssContent,
        ];

        return $this->renderView($theme, 'front', $data);
    }

    /**
     * Render the back face (Verso) of a card.
     *
     * @param BusinessCard $card
     * @param CardVisibility|null $visibility
     * @param bool $standalone
     * @param bool $inlineCss
     * @return string Rendered HTML
     */
    public function renderBack(
        BusinessCard $card,
        ?CardVisibility $visibility = null,
        bool $standalone = true,
        bool $inlineCss = false
    ): string {
        $theme = $this->resolveThemeKey($card->themeKey);
        $publicId = (string) $card->publicId;
        $publicUrl = $this->qrService->buildPublicUrl($publicId);

        // Generate QR code SVG and PNG data URI for maximum compatibility
        $qrSvg = $this->qrService->generateSvg($publicId, 260, 0, 'M');
        $pngBytes = $this->qrService->generatePng($publicId, 260, 0, 'M');
        $qrPngDataUri = 'data:image/png;base64,' . base64_encode($pngBytes);

        $logoUrl = null;
        if (!empty($card->logoPath)) {
            $logoUrl = $this->resolveMediaUrl($card->logoPath, $inlineCss);
        }
        $companyName = !empty($card->companyName) ? trim((string) $card->companyName) : null;
        $companyAddress = !empty($card->companyAddress) ? trim((string) $card->companyAddress) : null;

        $cssContent = $inlineCss ? $this->getThemeCss($theme) : null;

        $data = [
            'card' => $card,
            'publicId' => $publicId,
            'publicUrl' => $publicUrl,
            'qrSvg' => $qrSvg,
            'qrPngDataUri' => $qrPngDataUri,
            'logoUrl' => $logoUrl,
            'companyName' => $companyName,
            'companyAddress' => $companyAddress,
            'theme' => $theme,
            'standalone' => $standalone,
            'inlineCss' => $cssContent,
        ];

        return $this->renderView($theme, 'back', $data);
    }

    /**
     * Resolve media path into either a base64 Data URI (for standalone/PDF rendering)
     * or a standard URL path (/uploads/{filename}).
     */
    public function resolveMediaUrl(string $filename, bool $preferDataUri = false): ?string
    {
        $filePath = $this->uploadDir . DIRECTORY_SEPARATOR . basename($filename);
        if (!file_exists($filePath)) {
            return null;
        }

        if ($preferDataUri) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo !== false ? finfo_file($finfo, $filePath) : 'image/png';
            if ($finfo !== false) {
                finfo_close($finfo);
            }
            $content = file_get_contents($filePath);
            if ($content !== false) {
                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        return url('/uploads/' . rawurlencode(basename($filename)));
    }

    /**
     * Retrieve CardVisibility entity for a given card.
     */
    public function getCardVisibility(BusinessCard $card): CardVisibility
    {
        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        if ($visibility !== null) {
            return $visibility;
        }

        return new CardVisibility(
            id: null,
            cardId: (int) $card->id,
            showPhoto: true,
            showJobTitle: true,
            showDepartment: true,
            showCompany: true,
            showCompanyAddress: true,
            showPhone: true,
            showEmail: true,
            showWebsite: true,
            showSocialLinks: true
        );
    }

    /**
     * Load raw CSS stylesheet for a theme and normalize relative font paths to root-relative.
     */
    public function getThemeCss(string $theme): string
    {
        $defaultCssFile = $this->assetsPath . '/css/card-default.css';
        $defaultCss = file_exists($defaultCssFile) ? (string) file_get_contents($defaultCssFile) : '';

        if ($theme === 'default') {
            $css = $defaultCss;
        } else {
            $themeCssFile = $this->assetsPath . '/css/card-' . $theme . '.css';
            $themeCss = file_exists($themeCssFile) ? (string) file_get_contents($themeCssFile) : '';
            // Strip out @import url('card-default.css'); to prevent broken relative imports in temp files
            $themeCss = preg_replace('/@import\s+url\([\'"]?card-default\.css[\'"]?\);?/i', '', $themeCss);
            $css = $defaultCss . "\n\n/* ================= Theme: {$theme} Overrides ================= */\n" . $themeCss;
        }

        // Normalize relative font paths "../fonts/" to root-relative "/assets/fonts/"
        $css = str_replace('../fonts/', url('/assets/fonts/'), $css);

        return $css;
    }

    /**
     * Validate and sanitize theme key.
     */
    private function resolveThemeKey(?string $themeKey): string
    {
        return CardThemeService::normalize($themeKey);
    }

    /**
     * Render template view with sandboxed variable scope.
     *
     * @param string $theme
     * @param string $side 'front' or 'back'
     * @param array<string, mixed> $data
     * @return string
     */
    private function renderView(string $theme, string $side, array $data): string
    {
        $templatePath = $this->viewsPath . '/' . $theme . '/' . $side . '.php';
        if (!file_exists($templatePath)) {
            // Fallback to default
            $templatePath = $this->viewsPath . '/default/' . $side . '.php';
        }

        if (!file_exists($templatePath)) {
            throw new RuntimeException("Gabarit de carte introuvable : {$templatePath}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $templatePath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
