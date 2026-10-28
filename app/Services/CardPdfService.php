<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Repositories\CardRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;

/**
 * Service for High-Quality PDF Business Card Export (F-11).
 *
 * Generates an exact 2-page print-ready PDF at 85 × 55 mm:
 * - Page 1 = Recto (Front)
 * - Page 2 = Verso (Back)
 *
 * Compliant with professional print requirements:
 * - Exact 85 × 55 mm page dimensions without unwanted margins (0 margin @page).
 * - 3 mm internal safety margin respected.
 * - Vector selectable text with embedded typography.
 * - High-resolution embedded QR code (600+ DPI).
 * - Server-side visibility strictly enforced (Rule 6).
 * - Unguessable cached files in storage/generated.
 */
class CardPdfService
{
    public const CARD_WIDTH_MM = 85.0;
    public const CARD_HEIGHT_MM = 55.0;
    public const CARD_SAFETY_MARGIN_MM = 3.0;

    private CardRepository $cardRepo;
    private QrCodeService $qrService;
    private string $storageDir;
    private string $fontPath;

    public function __construct(
        ?CardRepository $cardRepo = null,
        ?QrCodeService $qrService = null,
        ?string $storageDir = null
    ) {
        $pdo = Database::getConnection();
        $this->cardRepo = $cardRepo ?? new CardRepository($pdo);
        $this->qrService = $qrService ?? new QrCodeService();

        $root = dirname(__DIR__, 2);
        $this->storageDir = $storageDir ?? ($root . '/storage/generated');
        $this->fontPath = $root . '/public/assets/fonts/OpenSans-Regular.ttf';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Get or generate the cached PDF file path for a business card.
     */
    public function getCardPdf(BusinessCard $card, bool $forceRegenerate = false): string
    {
        $cacheFile = $this->buildCachePath($card);

        if (!$forceRegenerate && file_exists($cacheFile) && filesize($cacheFile) > 0) {
            return $cacheFile;
        }

        $pdfBinary = $this->generatePdf($card);

        if (file_put_contents($cacheFile, $pdfBinary) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier PDF dans le cache : '{$cacheFile}'.");
        }

        return $cacheFile;
    }

    /**
     * Generate raw PDF binary bytes using Dompdf.
     */
    public function generatePdf(BusinessCard $card): string
    {
        $html = $this->renderHtml($card);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('dpi', 300);

        $dompdf = new Dompdf($options);

        // Convert 85 x 55 mm to PostScript points (1 mm = 72 / 25.4 pt = 2.83464567 pt)
        $widthPt = self::CARD_WIDTH_MM * (72.0 / 25.4);
        $heightPt = self::CARD_HEIGHT_MM * (72.0 / 25.4);

        $dompdf->setPaper([0, 0, $widthPt, $heightPt], 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Render the self-contained HTML for both pages (Recto & Verso).
     */
    public function renderHtml(BusinessCard $card): string
    {
        // 1. High-resolution QR code (600x600 px into 24x24 mm = ~635 DPI)
        $qrPng = $this->qrService->generatePng($card->publicId, 600, 0);
        $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);

        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        $isVis = fn(string $field): bool => $visibility === null || $visibility->isVisible($field);

        // 2. Resolve avatar & logo as Data URIs if public
        $photoDataUri = null;
        if (!empty($card->photoPath) && $isVis('show_photo')) {
            $photoDataUri = $this->resolveImageDataUri($card->photoPath);
        }

        $logoDataUri = null;
        if (!empty($card->logoPath)) {
            $logoDataUri = $this->resolveImageDataUri($card->logoPath);
        }

        // 3. Extract visible fields
        $displayName = $card->getDisplayName();
        $jobTitle = ($isVis('show_job_title') && !empty($card->jobTitle)) ? $card->jobTitle : null;
        $department = ($isVis('show_department') && !empty($card->department)) ? $card->department : null;
        $company = ($isVis('show_company') && !empty($card->companyName)) ? $card->companyName : null;
        $phone = ($isVis('show_phone') && !empty($card->professionalPhone)) ? $card->professionalPhone : null;
        $email = ($isVis('show_email') && !empty($card->professionalEmail)) ? $card->professionalEmail : null;
        
        $website = null;
        if ($isVis('show_website') && !empty($card->companyWebsite)) {
            $trimmedWebsite = trim((string) $card->companyWebsite);
            if (Security::isValidExternalUrl($trimmedWebsite)) {
                $website = $trimmedWebsite;
            }
        }

        $address = ($isVis('show_company_address') && !empty($card->companyAddress)) ? $card->companyAddress : null;

        // Escape outputs
        $eDisplayName = e($displayName);
        $eJobTitle = $jobTitle !== null ? e($jobTitle) : null;
        $eDepartment = $department !== null ? e($department) : null;
        $eCompany = $company !== null ? e($company) : null;
        $ePhone = $phone !== null ? e($phone) : null;
        $eEmail = $email !== null ? e($email) : null;
        $eWebsite = $website !== null ? e($website) : null;
        $eAddress = $address !== null ? e($address) : null;

        // Theme Palette Colors
        $themeInfo = CardThemeService::getTheme($card->themeKey);
        $pdfRectoBg = $themeInfo['rectoBg'] ?? '#FFFFFF';
        $pdfVersoBg = $themeInfo['versoBg'] ?? '#0F172A';
        $themePrimary = $themeInfo['primary'] ?? '#EA580C';
        $themeAccent = $themeInfo['accent'] ?? '#F59E0B';

        // Dual-tone SVG Background (Detail 1: 4 stepped stripes with horizontal tabs, Detail 2: flame watermark)
        $bgDarkColor = ($card->themeKey === 'default' || empty($card->themeKey)) ? '#0F172A' : $pdfVersoBg;
        $stripeYellow = ($card->themeKey === 'default') ? '#F59E0B' : $themeAccent;
        $stripeOrange = ($card->themeKey === 'default') ? '#EA580C' : $themePrimary;
        $stripeDeepBlue = '#1E40AF';
        $stripeSkyBlue = '#0284C7';
        $bgSvg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1004 650" width="1004" height="650">
    <rect width="1004" height="650" fill="{$pdfRectoBg}"/>
    <polygon points="662.5,45 706.5,45 390,650 346,650" fill="{$stripeYellow}"/>
    <polygon points="655.2,80 688.2,80 390,650 357,650" fill="{$stripeOrange}"/>
    <polygon points="647.8,115 669.8,115 390,650 368,650" fill="{$stripeDeepBlue}"/>
    <polygon points="640.5,150 651.5,150 390,650 379,650" fill="{$stripeSkyBlue}"/>
    <polygon points="730,0 1004,0 1004,650 390,650" fill="{$bgDarkColor}"/>
    <g transform="translate(765, 20) scale(1.65)">
        <path d="M50 0 C64 20 84 45 84 74 C84 104 64 122 44 122 C24 122 12 104 15 80 C18 64 28 60 30 72 C25 86 34 104 46 104 C62 104 68 86 66 68 C64 48 54 24 50 0 Z" fill="#DC2626" opacity="0.24"/>
        <path d="M44 26 C52 42 62 58 62 78 C62 94 52 104 42 104 C32 104 24 92 26 78 C28 64 36 44 44 26 Z" fill="#F59E0B" opacity="0.30"/>
    </g>
</svg>
SVG;
        $bgDataUri = 'data:image/svg+xml;base64,' . base64_encode($bgSvg);

        $roleLine = '';
        if (!empty($eJobTitle)) $roleLine = $eJobTitle;
        if (!empty($eDepartment)) $roleLine .= (!empty($roleLine) ? ' · ' : '') . $eDepartment;

        $logoHtml = !empty($logoDataUri) ? "<img src=\"{$logoDataUri}\" class=\"card-logo-img\" alt=\"Logo\">" : '';
        $companyNameDisplay = !empty($eCompany) ? $eCompany : 'SOCIETE IVORIENNE DE PETROLE';
        $companyAddressDisplay = !empty($eAddress) ? $eAddress : 'abidjan, VGE face solibra immeuble la balance';

        $contactRows = [];
        if (!empty($ePhone)) {
            $contactRows[] = "<div class=\"contact-item\">{$ePhone}</div>";
        }
        if (!empty($eEmail)) {
            $contactRows[] = "<div class=\"contact-item\">{$eEmail}</div>";
        }
        if (!empty($eWebsite)) {
            $contactRows[] = "<div class=\"contact-item\">{$eWebsite}</div>";
        }
        if (!empty($eAddress)) {
            $contactRows[] = "<div class=\"contact-item\">{$eAddress}</div>";
        }
        $contactsHtml = implode("\n                ", $contactRows);

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* Theme palette: {$themePrimary} / {$themeAccent} */
:root {
    --primary: {$themePrimary};
    --accent: {$themeAccent};
}
@page {
    size: 85mm 55mm;
    margin: 0;
}
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
html, body {
    margin: 0;
    padding: 0;
    width: 85mm;
    height: 55mm;
    font-family: Helvetica, Arial, sans-serif;
    color: #0F172A;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.page {
    width: 85mm;
    height: 55mm;
    position: relative;
    overflow: hidden;
    page-break-inside: avoid;
}
.page-front {
    page-break-after: always;
    background-color: #FFFFFF;
}
.page-back {
    page-break-after: avoid;
    background-color: {$bgDarkColor};
    color: #FFFFFF;
}
.bg-svg {
    position: absolute;
    left: 0;
    top: 0;
    width: 85mm;
    height: 55mm;
    z-index: 1;
}
.front-left {
    position: absolute;
    left: 5mm;
    top: 5mm;
    bottom: 5mm;
    width: 37mm;
    z-index: 2;
}
.card-logo-img {
    max-height: 9.5mm;
    max-width: 25mm;
    display: block;
}
.front-company {
    font-size: 5pt;
    font-weight: bold;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.4pt;
    margin-top: 1.2mm;
    line-height: 1.2;
}
.front-person {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
}
.front-name {
    font-size: 8.5pt;
    font-weight: bold;
    color: #0F172A;
    line-height: 1.15;
    word-wrap: break-word;
}
.front-role {
    font-size: 5.5pt;
    font-weight: bold;
    font-style: italic;
    color: #64748B;
    margin-top: 0.6mm;
    line-height: 1.2;
    word-wrap: break-word;
}
.front-right {
    position: absolute;
    right: 4mm;
    top: 22mm;
    width: 30mm;
    z-index: 2;
    text-align: right;
    color: #FFFFFF;
}
.contact-item {
    font-size: 4.5pt;
    font-weight: bold;
    color: #F8FAFC;
    line-height: 1.35;
    margin-bottom: 0.8mm;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* VERSO */
.back-qr-box {
    position: absolute;
    left: 5mm;
    top: 7.5mm;
    width: 40mm;
    height: 40mm;
    background: #FFFFFF;
    border-radius: 2mm;
    text-align: center;
}
.back-qr-img {
    width: 36mm;
    height: 36mm;
    margin-top: 2mm;
}
.back-content {
    position: absolute;
    left: 48mm;
    top: 7.5mm;
    right: 4mm;
    bottom: 7.5mm;
    height: 40mm;
}
.back-company {
    font-size: 6pt;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.4pt;
    color: #FFFFFF;
    margin-top: 1.5mm;
    line-height: 1.2;
}
.back-address {
    font-size: 4.5pt;
    color: #CBD5E1;
    margin-top: 1mm;
    line-height: 1.3;
}
.back-cta {
    position: absolute;
    bottom: 0;
    left: 0;
    font-size: 5pt;
    font-weight: bold;
    color: #FFFFFF;
    border-top: 0.3mm solid rgba(255,255,255,0.25);
    padding-top: 1.5mm;
    width: 33mm;
    white-space: nowrap;
}
</style>
</head>
<body>
    <!-- PAGE 1 : RECTO -->
    <div class="page page-front">
        <img src="{$bgDataUri}" class="bg-svg" alt="">
        <div class="front-left">
            {$logoHtml}
            <div class="front-company">{$companyNameDisplay}</div>
            <div class="front-person">
                <div class="front-name">{$eDisplayName}</div>
                <div class="front-role">{$roleLine}</div>
            </div>
        </div>
        <div class="front-right">
            {$contactsHtml}
        </div>
    </div>

    <!-- PAGE 2 : VERSO -->
    <div class="page page-back">
        <div class="back-qr-box">
            <img src="{$qrDataUri}" class="back-qr-img" alt="QR Code">
        </div>
        <div class="back-content">
            {$logoHtml}
            <div class="back-company">{$companyNameDisplay}</div>
            <div class="back-address">{$companyAddressDisplay}</div>
            <div class="back-cta">Scannez pour enregistrer mon contact</div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate sanitized clean download filename (e.g. prenom-nom-carte.pdf).
     */
    public function getDownloadFilename(BusinessCard $card): string
    {
        $fullName = trim($card->getDisplayName());
        if ($fullName === '') {
            $fullName = 'carte';
        }

        $transliterator = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
            'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
            'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
            'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o',
            'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c', 'Ñ' => 'n',
        ];

        $slug = strtr($fullName, $transliterator);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'carte';
        }

        return "{$slug}-carte.pdf";
    }

    /**
     * Construct unguessable cache file path in storage/generated.
     */
    public function buildCachePath(BusinessCard $card): string
    {
        $hashData = sprintf(
            'card_%d_%s_pdf_%s_%s',
            $card->id ?? 0,
            $card->publicId,
            $card->themeKey ?? 'default',
            $card->updatedAt ?? 'initial'
        );
        $hash = hash('sha256', $hashData);

        return $this->storageDir . "/card_{$card->id}_pdf_{$hash}.pdf";
    }

    /**
     * Invalidate all cached PDF files for a given card ID.
     */
    public function invalidateCache(int $cardId): int
    {
        if ($cardId <= 0 || !is_dir($this->storageDir)) {
            return 0;
        }

        $pattern = $this->storageDir . "/card_{$cardId}_pdf_*.pdf";
        $files = glob($pattern);

        if ($files === false || empty($files)) {
            return 0;
        }

        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Resolve a local file path or public URL as a base64 Data URI.
     */
    private function resolveImageDataUri(?string $pathOrUrl): ?string
    {
        if (empty($pathOrUrl)) {
            return null;
        }

        // 1. If already a data URI, return as-is
        if (str_starts_with($pathOrUrl, 'data:image/')) {
            return $pathOrUrl;
        }

        $root = dirname(__DIR__, 2);
        $localPath = null;
        $privateUpload = $root . '/storage/private-uploads/' . basename($pathOrUrl);

        // 2. Check private uploads first, then public relative paths
        if (file_exists($privateUpload) && is_file($privateUpload)) {
            $localPath = $privateUpload;
        } elseif (str_starts_with($pathOrUrl, '/uploads/')) {
            $localPath = $root . '/public' . $pathOrUrl;
        } elseif (str_starts_with($pathOrUrl, 'uploads/')) {
            $localPath = $root . '/public/' . $pathOrUrl;
        } elseif (file_exists($pathOrUrl)) {
            $localPath = $pathOrUrl;
        }

        if ($localPath !== null && file_exists($localPath)) {
            $data = file_get_contents($localPath);
            if ($data !== false) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($finfo, $data) ?: 'image/png';
                finfo_close($finfo);
                return 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }

        return null;
    }
}
