<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\CardController;
use App\Core\Database;
use App\Core\Request;
use App\Models\BusinessCard;
use App\Models\User;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardImageService;
use App\Services\CardPdfService;
use App\Services\CardRenderService;
use App\Services\CardService;
use App\Services\QrCodeService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Automated Test Suite for PDF Card Export (F-11).
 *
 * Verifies:
 * - PDF contains exactly 2 pages (page 1 = recto, page 2 = verso).
 * - Exact dimensions 85 × 55 mm without parasitic margins.
 * - Text is vector and selectable (decompressed text streams).
 * - QR code is scannable from the PDF image stream and decodes to stable public URL.
 * - Download filename sanitization.
 * - Cache generation and invalidation on card update.
 * - Authentication & ownership enforcement on GET /card/download/card.pdf.
 */
class CardExportPdfTest extends TestCase
{
    private \PDO $pdo;
    private CardRepository $cardRepo;
    private UserRepository $userRepo;
    private CardPdfService $pdfService;
    private CardService $cardService;
    private QrCodeService $qrService;
    private AuthService $authService;
    private string $storageDir;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        $this->cardRepo = new CardRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
        $this->authService = new AuthService($this->pdo, $this->userRepo, $this->cardRepo);
        $this->qrService = new QrCodeService('http://localhost:8000');

        $this->storageDir = dirname(__DIR__) . '/storage/generated';
        $this->pdfService = new CardPdfService($this->cardRepo, $this->qrService, $this->storageDir);
        $this->cardService = new CardService($this->pdo, $this->cardRepo);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    private function createTestUserWithCard(string $prefix, string $firstName, string $lastName): array
    {
        $email = $prefix . '_' . bin2hex(random_bytes(4)) . '@scanv.test';
        $user = $this->authService->register([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'login_email' => $email,
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];
        $card = $this->cardRepo->findByUserId((int) $user->id);

        $this->pdo->prepare("
            UPDATE business_cards
            SET display_first_name = :first,
                display_last_name = :last,
                job_title = 'Ingénieur Systèmes',
                company_name = 'ScanV Corp',
                professional_phone = '+33 6 12 34 56 78',
                professional_email = :email,
                company_website = 'https://scanv.test',
                company_address = '10 Rue de la Paix, 75002 Paris',
                is_active = 1
            WHERE id = :id
        ")->execute([
            ':first' => $firstName,
            ':last' => $lastName,
            ':email' => $email,
            ':id' => $card->id,
        ]);

        $this->pdo->prepare("
            UPDATE card_visibility
            SET show_job_title = 1,
                show_company = 1,
                show_phone = 1,
                show_email = 1,
                show_website = 1,
                show_company_address = 1
            WHERE card_id = :id
        ")->execute([':id' => $card->id]);

        $freshCard = $this->cardRepo->findById((int) $card->id);

        return [$user, $freshCard];
    }

    public function testPdfHasExactTwoPagesAndDimensions(): void
    {
        /** @var User $user */
        /** @var BusinessCard $card */
        [$user, $card] = $this->createTestUserWithCard('pdf_geom', 'Alexandre', 'Dupont');

        $pdfBytes = $this->pdfService->generatePdf($card);
        $this->assertNotEmpty($pdfBytes, "PDF generation should output binary data.");

        // 1. Check page count: exactly 2 pages
        preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes, $matches);
        $this->assertEquals(2, count($matches[0]), "The generated PDF must have exactly 2 pages (recto & verso).");

        // 2. Check MediaBox dimensions: 85 mm x 55 mm
        // 85 mm in points = 85 * 72 / 25.4 = 240.945 pt
        // 55 mm in points = 55 * 72 / 25.4 = 155.906 pt
        preg_match_all('/\/MediaBox\s*\[\s*([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s*\]/', $pdfBytes, $mbMatches);
        $this->assertNotEmpty($mbMatches[0], "MediaBox must be defined in PDF.");

        $widthPt = (float) $mbMatches[3][0];
        $heightPt = (float) $mbMatches[4][0];

        $widthMm = $widthPt * (25.4 / 72.0);
        $heightMm = $heightPt * (25.4 / 72.0);

        $this->assertEqualsWithDelta(85.0, $widthMm, 0.5, "Page width must be 85 mm.");
        $this->assertEqualsWithDelta(55.0, $heightMm, 0.5, "Page height must be 55 mm.");
    }

    public function testPdfTextIsVectorAndSelectable(): void
    {
        /** @var User $user */
        /** @var BusinessCard $card */
        [$user, $card] = $this->createTestUserWithCard('pdf_text', 'Béatrice', 'Lefebvre');

        $pdfBytes = $this->pdfService->generatePdf($card);

        // Decompress PDF streams to locate vector text operators
        preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $pdfBytes, $streams);
        $this->assertNotEmpty($streams[1], "PDF must contain content streams.");

        $allDecompressedText = '';
        foreach ($streams[1] as $stream) {
            $uncompressed = @gzuncompress($stream);
            if ($uncompressed !== false) {
                $allDecompressedText .= ' ' . $uncompressed;
            }
        }

        // Verify text presence in streams (vector selectable text)
        $this->assertTrue(
            str_contains(strtoupper($allDecompressedText), 'LEFEBVRE') || str_contains($allDecompressedText, 'Lefebvre'),
            "Cardholder name must be present as vector text in PDF streams."
        );
        $this->assertTrue(
            str_contains($allDecompressedText, 'ScanV Corp') || str_contains($allDecompressedText, 'SCANV'),
            "Company name / brand must be present as vector text in PDF streams."
        );
        $this->assertTrue(
            str_contains($allDecompressedText, 'Scannez pour enregistrer mon contact'),
            "Verso call-to-action text must be present as vector text."
        );
    }

    public function testPdfContainsScannableQrCode(): void
    {
        /** @var User $user */
        /** @var BusinessCard $card */
        [$user, $card] = $this->createTestUserWithCard('pdf_qr', 'Charles', 'Baudelaire');

        $pdfPath = $this->pdfService->getCardPdf($card, forceRegenerate: true);
        $this->assertFileExists($pdfPath);
        $pdfBytes = (string) file_get_contents($pdfPath);

        $decodedUrl = null;
        $objects = explode('endobj', $pdfBytes);

        foreach ($objects as $obj) {
            if (!str_contains($obj, '/Subtype /Image')) {
                continue;
            }

            if (!preg_match('/\/Length\s+(\d+)/', $obj, $lenMatch)) {
                continue;
            }
            $streamLength = (int) $lenMatch[1];

            $streamKeyword = "stream\r\n";
            $streamPos = strpos($obj, $streamKeyword);
            if ($streamPos === false) {
                $streamKeyword = "stream\n";
                $streamPos = strpos($obj, $streamKeyword);
            }
            if ($streamPos === false) {
                continue;
            }

            $rawStream = substr($obj, $streamPos + strlen($streamKeyword), $streamLength);

            $isRgb = str_contains($obj, '/ColorSpace /DeviceRGB') || str_contains($obj, '/Colors 3');
            $colorType = $isRgb ? 2 : 0;

            // Reconstruct standard PNG from PDF image stream
            $ihdrData = pack('NNCCCCC', 600, 600, 8, $colorType, 0, 0, 0);
            $ihdrChunk = pack('N', 13) . 'IHDR' . $ihdrData . pack('N', crc32('IHDR' . $ihdrData));
            $idatChunk = pack('N', strlen($rawStream)) . 'IDAT' . $rawStream . pack('N', crc32('IDAT' . $rawStream));
            $iendChunk = pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));
            $pngBytes = "\x89PNG\r\n\x1a\n" . $ihdrChunk . $idatChunk . $iendChunk;

            try {
                $options = new QROptions();
                $options->readerUseImagickIfAvailable = false;
                $qrcode = new QRCode($options);
                $result = $qrcode->readFromBlob($pngBytes);
                $decodedText = (string) $result;
                if (!empty($decodedText)) {
                    $decodedUrl = $decodedText;
                    break;
                }
            } catch (\Throwable) {
                // Skip masks / alpha streams
            }
        }

        $this->assertNotNull($decodedUrl, "QR code image from PDF must be decodable.");
        $expectedUrl = 'http://localhost:8000/c/' . $card->publicId;
        $this->assertEquals($expectedUrl, $decodedUrl, "Decoded QR URL must strictly match public URL (Rule 7).");
    }

    public function testPdfDownloadFilenameSanitization(): void
    {
        /** @var User $user */
        /** @var BusinessCard $card */
        [$user, $card] = $this->createTestUserWithCard('pdf_name', 'Hélène', 'd\'Artois-Béthune');

        $filename = $this->pdfService->getDownloadFilename($card);

        $this->assertEquals('helene-d-artois-bethune-carte.pdf', $filename, "Filename must be sanitized, lowercase, with accents transliterated.");
    }

    public function testPdfCacheGenerationAndInvalidationOnCardUpdate(): void
    {
        /** @var User $user */
        /** @var BusinessCard $card */
        [$user, $card] = $this->createTestUserWithCard('pdf_cache', 'David', 'Goliath');

        // 1. Generate and cache
        $cachedPath = $this->pdfService->getCardPdf($card);
        $this->assertFileExists($cachedPath, "PDF must be written to cache.");

        // 2. Fetching again returns cached file without regenerating
        $cachedPath2 = $this->pdfService->getCardPdf($card);
        $this->assertEquals($cachedPath, $cachedPath2);

        // 3. Update card through CardService
        $this->cardService->updateCard((int) $user->id, [
            'display_first_name' => 'David Modifié',
            'display_last_name' => 'Goliath',
            'theme_key' => 'default',
        ]);

        // 4. Verify previous cache file was invalidated / unlinked
        $this->assertFalse(file_exists($cachedPath), "Old PDF cache file must be unlinked when card is updated.");
    }

    public function testPdfDownloadRouteEnforcesAuthenticationAndOwnership(): void
    {
        /** @var User $userA */
        /** @var BusinessCard $cardA */
        [$userA, $cardA] = $this->createTestUserWithCard('pdf_owner_a', 'Alice', 'Wonder');

        /** @var User $userB */
        /** @var BusinessCard $cardB */
        [$userB, $cardB] = $this->createTestUserWithCard('pdf_owner_b', 'Bob', 'Builder');

        $controller = new CardController(
            cardService: $this->cardService,
            cardPdfService: $this->pdfService
        );

        // 1. Unauthenticated request -> Redirect to login
        $_SESSION = [];
        $req = new Request('GET', '/card/download/card.pdf');
        $resp = $controller->downloadPdf($req);
        $this->assertEquals(302, $resp->getStatusCode(), "Unauthenticated download must redirect to login.");

        // 2. Authenticated as User A -> Can download User A's card
        $_SESSION['user_id'] = $userA->id;
        $respA = $controller->downloadPdf($req);
        $this->assertEquals(200, $respA->getStatusCode());
        $this->assertEquals('application/pdf', $respA->getHeaders()['Content-Type']);
        $this->assertStringContainsString('alice-wonder-carte.pdf', $respA->getHeaders()['Content-Disposition']);

        // 3. Authenticated as User B -> Gets Bob's card, never Alice's
        $_SESSION['user_id'] = $userB->id;
        $respB = $controller->downloadPdf($req);
        $this->assertEquals(200, $respB->getStatusCode());
        $this->assertStringContainsString('bob-builder-carte.pdf', $respB->getHeaders()['Content-Disposition']);
    }

    private function assertEqualsWithDelta(float $expected, float $actual, float $delta, string $message = ''): void
    {
        $this->assertTrue(abs($expected - $actual) <= $delta, $message . " Expected {$expected} ± {$delta}, got {$actual}.");
    }
}
