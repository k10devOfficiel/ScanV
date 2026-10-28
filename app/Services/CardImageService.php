<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\CardVisibility;
use App\Repositories\CardRepository;
use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Service managing physical card PNG exports (F-10).
 * Produces crisp 1004 × 650 px (300 DPI) PNG files for Recto and Verso.
 *
 * Primary engine: Headless browser (Chrome / Chromium / Edge) for 100% pixel-perfect CSS rendering.
 * Fallback engine: Pure PHP GD extension for autonomous generation on hosting environments without headless browsers.
 */
class CardImageService
{
    public const CARD_WIDTH = 1004;
    public const CARD_HEIGHT = 650;
    public const CARD_DPI = 300;

    private CardRepository $cardRepo;
    private CardRenderService $renderService;
    private QrCodeService $qrService;
    private string $storageDir;
    private ?string $browserPath;
    private string $fontPath;

    public function __construct(
        ?CardRepository $cardRepo = null,
        ?CardRenderService $renderService = null,
        ?QrCodeService $qrService = null,
        ?string $storageDir = null,
        ?string $browserPath = null
    ) {
        $pdo = Database::getConnection();
        $this->cardRepo = $cardRepo ?? new CardRepository($pdo);
        $this->qrService = $qrService ?? new QrCodeService();
        $this->renderService = $renderService ?? new CardRenderService($this->cardRepo, $this->qrService);

        $root = dirname(__DIR__, 2);
        $this->storageDir = $storageDir ?? ($root . '/storage/generated');
        $this->fontPath = $root . '/public/assets/fonts/OpenSans-Regular.ttf';

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }

        $this->browserPath = $browserPath ?? $this->detectBrowserBinary();
    }

    /**
     * Get or generate the cached PNG file path for a card side ('front' or 'back').
     *
     * @param BusinessCard $card
     * @param string $side 'front' or 'back'
     * @param bool $forceRegenerate
     * @return string Absolute file path to the generated PNG
     */
    public function getCardPng(BusinessCard $card, string $side, bool $forceRegenerate = false): string
    {
        $normalizedSide = strtolower(trim($side));
        if (!in_array($normalizedSide, ['front', 'back'], true)) {
            throw new InvalidArgumentException("Face de carte invalide ('{$side}'). Valeurs permises : 'front' ou 'back'.");
        }

        $cacheFile = $this->buildCachePath($card, $normalizedSide);

        if (!$forceRegenerate && file_exists($cacheFile) && filesize($cacheFile) > 0) {
            return $cacheFile;
        }

        return $this->generateCardPng($card, $normalizedSide, $cacheFile);
    }

    /**
     * Generate the card PNG to the specified destination path.
     */
    public function generateCardPng(BusinessCard $card, string $side, ?string $destinationPath = null): string
    {
        $outputPath = $destinationPath ?? $this->buildCachePath($card, $side);

        // Try primary engine: Headless browser
        $rendered = false;
        if ($this->browserPath !== null) {
            $rendered = $this->renderWithBrowser($card, $side, $outputPath);
        }

        // Fallback engine: Native PHP GD
        if (!$rendered || !file_exists($outputPath) || filesize($outputPath) === 0) {
            $this->renderWithGd($card, $side, $outputPath);
        }

        // Embed 300 DPI resolution metadata into the PNG
        if (file_exists($outputPath)) {
            $rawPng = file_get_contents($outputPath);
            if ($rawPng !== false) {
                $dpiPng = self::injectDpi($rawPng, self::CARD_DPI);
                file_put_contents($outputPath, $dpiPng);
            }
        }

        return $outputPath;
    }

    /**
     * Render card using headless Chrome / Edge browser.
     */
    public function renderWithBrowser(BusinessCard $card, string $side, string $outputPath): bool
    {
        if ($this->browserPath === null) {
            return false;
        }

        // 1. Render self-contained standalone HTML with inlined CSS
        $html = ($side === 'front')
            ? $this->renderService->renderFront($card, standalone: true, inlineCss: true)
            : $this->renderService->renderBack($card, standalone: true, inlineCss: true);

        $tempHtml = $this->storageDir . '/temp_' . bin2hex(random_bytes(8)) . '.html';
        if (file_put_contents($tempHtml, $html) === false) {
            return false;
        }

        // 2. Execute headless screenshot command
        $width = self::CARD_WIDTH;
        $height = self::CARD_HEIGHT;

        $cmd = sprintf(
            '"%s" --headless=new --disable-gpu --no-sandbox --hide-scrollbars --window-size=%d,%d --screenshot="%s" "%s"',
            $this->browserPath,
            $width,
            $height,
            $outputPath,
            $tempHtml
        );

        $output = [];
        $returnCode = 1;
        @exec($cmd, $output, $returnCode);

        // Clean up temporary HTML
        @unlink($tempHtml);

        return ($returnCode === 0 && file_exists($outputPath) && filesize($outputPath) > 0);
    }

    /**
     * Fallback rendering engine using pure PHP GD extension (zero external binary dependency).
     */
    public function renderWithGd(BusinessCard $card, string $side, string $outputPath): void
    {
        $im = imagecreatetruecolor(self::CARD_WIDTH, self::CARD_HEIGHT);
        if (!$im instanceof GdImage) {
            throw new RuntimeException("Impossible d'initialiser une image GD.");
        }

        imagealphablending($im, true);
        imagesavealpha($im, true);

        // Colors
        $white = imagecolorallocate($im, 255, 255, 255);
        $navy = imagecolorallocate($im, 0, 35, 111);
        $dark = imagecolorallocate($im, 19, 27, 46);
        $muted = imagecolorallocate($im, 100, 116, 139);
        $lightBlue = imagecolorallocate($im, 220, 225, 255);

        if ($side === 'back') {
            // ================= VERSO GD RENDERING =================
            imagefilledrectangle($im, 0, 0, self::CARD_WIDTH, self::CARD_HEIGHT, $navy);

            // Brand header
            $font = file_exists($this->fontPath) ? $this->fontPath : null;
            if ($font !== null) {
                imagettftext($im, 18, 0, 45, 60, $white, $font, 'SCANV');
                imagettftext($im, 12, 0, self::CARD_WIDTH - 220, 60, $lightBlue, $font, 'NFC & QR DIGITAL');
            }

            // White QR Code Box with quiet zone
            $boxSize = 300;
            $boxX = (int) ((self::CARD_WIDTH - $boxSize) / 2);
            $boxY = (int) ((self::CARD_HEIGHT - $boxSize) / 2) - 20;
            imagefilledrectangle($im, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $white);

            // Generate clean QR code PNG and stamp onto box
            $qrPngBytes = $this->qrService->generatePng((string) $card->publicId, 260, 0, 'M');
            $qrIm = @imagecreatefromstring($qrPngBytes);
            if ($qrIm instanceof GdImage) {
                $qrX = $boxX + 20;
                $qrY = $boxY + 20;
                imagecopyresampled($im, $qrIm, $qrX, $qrY, 0, 0, 260, 260, imagesx($qrIm), imagesy($qrIm));
                imagedestroy($qrIm);
            }

            // Footer prompt text
            if ($font !== null) {
                $promptText = 'Scannez pour enregistrer mon contact';
                $bbox = imagettfbbox(18, 0, $font, $promptText);
                $textWidth = abs($bbox[2] - $bbox[0]);
                $textX = (int) ((self::CARD_WIDTH - $textWidth) / 2);
                imagettftext($im, 18, 0, $textX, self::CARD_HEIGHT - 65, $white, $font, $promptText);

                $subText = 'Compatible avec tous les smartphones (iOS & Android)';
                $bboxSub = imagettfbbox(13, 0, $font, $subText);
                $subWidth = abs($bboxSub[2] - $bboxSub[0]);
                $subX = (int) ((self::CARD_WIDTH - $subWidth) / 2);
                imagettftext($im, 13, 0, $subX, self::CARD_HEIGHT - 35, $lightBlue, $font, $subText);
            }
        } else {
            // ================= RECTO GD RENDERING =================
            imagefilledrectangle($im, 0, 0, self::CARD_WIDTH, self::CARD_HEIGHT, $white);

            $visibility = $this->renderService->getCardVisibility($card);
            $hasPhoto = ($visibility->isVisible('show_photo') && !empty($card->photoPath));

            // Left decorative band
            $bandWidth = $hasPhoto ? 250 : 60;
            imagefilledrectangle($im, 0, 0, $bandWidth, self::CARD_HEIGHT, $navy);

            // Avatar photo (if visible and file exists)
            $contentLeft = $bandWidth + 50;
            if ($hasPhoto) {
                $photoPath = dirname(__DIR__, 2) . '/storage/private-uploads/' . basename($card->photoPath);
                if (file_exists($photoPath)) {
                    $photoIm = @imagecreatefromstring((string) file_get_contents($photoPath));
                    if ($photoIm instanceof GdImage) {
                        $avatarSize = 180;
                        $avatarX = $bandWidth - ($avatarSize / 2);
                        $avatarY = (int) ((self::CARD_HEIGHT - $avatarSize) / 2);
                        imagefilledrectangle($im, (int) $avatarX - 4, (int) $avatarY - 4, (int) ($avatarX + $avatarSize + 4), (int) ($avatarY + $avatarSize + 4), $white);
                        imagecopyresampled($im, $photoIm, (int) $avatarX, (int) $avatarY, 0, 0, $avatarSize, $avatarSize, imagesx($photoIm), imagesy($photoIm));
                        imagedestroy($photoIm);
                        $contentLeft = $bandWidth + 120;
                    }
                }
            }

            $font = file_exists($this->fontPath) ? $this->fontPath : null;
            if ($font !== null) {
                $y = 160;

                // Display Name
                $name = $card->getDisplayName();
                imagettftext($im, 26, 0, $contentLeft, $y, $dark, $font, mb_strtoupper($name));
                $y += 40;

                // Job title
                if ($visibility->isVisible('show_job_title') && !empty($card->jobTitle)) {
                    imagettftext($im, 16, 0, $contentLeft, $y, $navy, $font, (string) $card->jobTitle);
                    $y += 26;
                }

                // Department
                if ($visibility->isVisible('show_department') && !empty($card->department)) {
                    imagettftext($im, 13, 0, $contentLeft, $y, $muted, $font, (string) $card->department);
                    $y += 24;
                }

                // Divider line
                $y += 10;
                imagefilledrectangle($im, $contentLeft, $y, self::CARD_WIDTH - 60, $y + 2, $navy);
                $y += 35;

                // Company Name
                if ($visibility->isVisible('show_company') && !empty($card->companyName)) {
                    imagettftext($im, 18, 0, $contentLeft, $y, $dark, $font, mb_strtoupper((string) $card->companyName));
                    $y += 35;
                }

                // Phone
                if ($visibility->isVisible('show_phone') && !empty($card->professionalPhone)) {
                    imagettftext($im, 14, 0, $contentLeft, $y, $dark, $font, 'Tél : ' . $card->professionalPhone);
                    $y += 28;
                }

                // Email
                if ($visibility->isVisible('show_email') && !empty($card->professionalEmail)) {
                    imagettftext($im, 14, 0, $contentLeft, $y, $dark, $font, 'Email : ' . $card->professionalEmail);
                    $y += 28;
                }

                // Website
                if ($visibility->isVisible('show_website') && !empty($card->companyWebsite)) {
                    imagettftext($im, 14, 0, $contentLeft, $y, $dark, $font, 'Web : ' . $card->companyWebsite);
                    $y += 28;
                }
            }
        }

        imagepng($im, $outputPath, 9);
        imagedestroy($im);
    }

    /**
     * Invalidate and purge all cached PNG files for a given card ID.
     */
    public function invalidateCache(int $cardId): int
    {
        $pattern = $this->storageDir . '/card_' . $cardId . '_*.png';
        $files = glob($pattern);
        $count = 0;

        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Generate sanitized clean download filename (e.g. prenom-nom-recto.png).
     */
    public function getDownloadFilename(BusinessCard $card, string $side): string
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

        $suffix = ($side === 'front') ? 'recto' : 'verso';

        return "{$slug}-{$suffix}.png";
    }

    /**
     * Construct unguessable cache file path in storage/generated.
     */
    public function buildCachePath(BusinessCard $card, string $side): string
    {
        $hashData = sprintf(
            'card_%d_%s_%s_%s_%s',
            $card->id ?? 0,
            $card->publicId,
            $side,
            $card->themeKey ?? 'default',
            $card->updatedAt ?? 'initial'
        );
        $hash = hash('sha256', $hashData);

        return $this->storageDir . "/card_{$card->id}_{$side}_{$hash}.png";
    }

    /**
     * Detect installed headless browser (Google Chrome, Microsoft Edge, Chromium).
     */
    public function detectBrowserBinary(): ?string
    {
        // 1. Check custom environment variable
        $customBin = Env::get('CHROME_BIN') ?? Env::get('BROWSER_BIN');
        if (!empty($customBin) && file_exists((string) $customBin)) {
            return (string) $customBin;
        }

        // 2. Standard Windows installation paths
        $candidates = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            // Linux / Unix candidates
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Inject 300 DPI (pHYs chunk) physical resolution into raw PNG byte stream.
     */
    public static function injectDpi(string $pngData, int $dpi = 300): string
    {
        if (!str_starts_with($pngData, "\x89PNG\r\n\x1a\n")) {
            return $pngData;
        }

        // 300 dpi = 11811 pixels per meter
        $ppm = (int) round($dpi / 0.0254);
        $data = pack('NNc', $ppm, $ppm, 1);
        $chunk = pack('N', 9) . 'pHYs' . $data . pack('N', crc32('pHYs' . $data));

        if (strlen($pngData) < 33 || substr($pngData, 12, 4) !== 'IHDR') {
            return $pngData;
        }

        // Remove any existing pHYs chunk
        $clean = preg_replace('/.{4}pHYs.{9}.{4}/s', '', $pngData);
        if ($clean !== null) {
            $pngData = $clean;
        }

        return substr($pngData, 0, 33) . $chunk . substr($pngData, 33);
    }
}
