<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use InvalidArgumentException;

/**
 * Service generating business card QR codes (F-09).
 *
 * Strictly encodes the complete public card URL and nothing else (Rule 7).
 * Provides configurable outputs in PNG and SVG formats with M or Q error correction.
 */
class QrCodeService
{
    private string $baseUrl;

    public const DEFAULT_SIZE = 300;
    public const DEFAULT_MARGIN = 10;
    public const DEFAULT_ERROR_CORRECTION = 'M';

    public function __construct(?string $baseUrl = null)
    {
        if ($baseUrl !== null && $baseUrl !== '') {
            $this->baseUrl = rtrim($baseUrl, '/');
        } else {
            $appUrl = (string) Env::get('APP_URL', 'http://localhost:8000');
            $this->baseUrl = rtrim($appUrl, '/');
        }
    }

    /**
     * Build the complete public URL for a given public_id.
     * Format: APP_URL + /c/{public_id}
     *
     * @throws InvalidArgumentException if public_id is invalid (must be 32 hex chars, Rule 5).
     */
    public function buildPublicUrl(string $publicId): string
    {
        $cleanId = strtolower(trim($publicId));

        if (!preg_match('/^[a-f0-9]{32}$/', $cleanId)) {
            throw new InvalidArgumentException("Identifiant public invalide (32 caractères hexadécimaux requis).");
        }

        return $this->baseUrl . '/c/' . $cleanId;
    }

    /**
     * Map error correction level string ('M' or 'Q') to Endroid ErrorCorrectionLevel enum.
     *
     * @throws InvalidArgumentException if an unsupported level is requested.
     */
    public function mapErrorCorrectionLevel(string $level): ErrorCorrectionLevel
    {
        $normalized = strtoupper(trim($level));

        return match ($normalized) {
            'M', 'MEDIUM' => ErrorCorrectionLevel::Medium,
            'Q', 'QUARTILE' => ErrorCorrectionLevel::Quartile,
            default => throw new InvalidArgumentException(
                "Niveau de correction d'erreur non supporté : '{$level}'. Les niveaux autorisés sont 'M' (Medium) ou 'Q' (Quartile)."
            ),
        };
    }

    /**
     * Generate PNG binary data for a public business card ID.
     *
     * @param string $publicId 32 hex characters
     * @param int $size Width/height in pixels (default 300, min 50, max 2000)
     * @param int $margin Quiet zone in pixels (default 10, min 0, max 100)
     * @param string $errorCorrection 'M' or 'Q' (default 'M')
     * @return string Raw PNG binary bytes
     */
    public function generatePng(
        string $publicId,
        int $size = self::DEFAULT_SIZE,
        int $margin = self::DEFAULT_MARGIN,
        string $errorCorrection = self::DEFAULT_ERROR_CORRECTION
    ): string {
        $url = $this->buildPublicUrl($publicId);

        return $this->generatePngFromUrl($url, $size, $margin, $errorCorrection);
    }

    /**
     * Generate SVG XML string for a public business card ID.
     *
     * @param string $publicId 32 hex characters
     * @param int $size Width/height in pixels (default 300, min 50, max 2000)
     * @param int $margin Quiet zone in pixels (default 10, min 0, max 100)
     * @param string $errorCorrection 'M' or 'Q' (default 'M')
     * @return string SVG XML markup
     */
    public function generateSvg(
        string $publicId,
        int $size = self::DEFAULT_SIZE,
        int $margin = self::DEFAULT_MARGIN,
        string $errorCorrection = self::DEFAULT_ERROR_CORRECTION
    ): string {
        $url = $this->buildPublicUrl($publicId);

        return $this->generateSvgFromUrl($url, $size, $margin, $errorCorrection);
    }

    /**
     * Generate PNG binary data from a complete public URL.
     * Enforces that the payload is strictly an HTTP/HTTPS public card URL (Rule 7 & 8).
     */
    public function generatePngFromUrl(
        string $url,
        int $size = self::DEFAULT_SIZE,
        int $margin = self::DEFAULT_MARGIN,
        string $errorCorrection = self::DEFAULT_ERROR_CORRECTION
    ): string {
        $qrCode = $this->createQrCodeInstance($url, $size, $margin, $errorCorrection);
        $writer = new PngWriter();

        return $writer->write($qrCode)->getString();
    }

    /**
     * Generate SVG XML markup from a complete public URL.
     * Enforces that the payload is strictly an HTTP/HTTPS public card URL (Rule 7 & 8).
     */
    public function generateSvgFromUrl(
        string $url,
        int $size = self::DEFAULT_SIZE,
        int $margin = self::DEFAULT_MARGIN,
        string $errorCorrection = self::DEFAULT_ERROR_CORRECTION
    ): string {
        $qrCode = $this->createQrCodeInstance($url, $size, $margin, $errorCorrection);
        $writer = new SvgWriter();

        return $writer->write($qrCode)->getString();
    }

    /**
     * Helper to construct and validate the Endroid QrCode instance.
     */
    private function createQrCodeInstance(
        string $url,
        int $size,
        int $margin,
        string $errorCorrection
    ): QrCode {
        $trimmedUrl = trim($url);

        // Security check: Only http:// or https:// URLs allowed (Rule 7 & 8)
        if (!preg_match('~^https?://~i', $trimmedUrl)) {
            throw new InvalidArgumentException("L'URL encodée dans le QR code doit débuter par http:// ou https://.");
        }

        // Clamp size and margin to reasonable bounds
        $validSize = max(50, min(2000, $size));
        $validMargin = max(0, min(100, $margin));
        $ecLevel = $this->mapErrorCorrectionLevel($errorCorrection);

        return new QrCode(
            data: $trimmedUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: $ecLevel,
            size: $validSize,
            margin: $validMargin,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );
    }
}
