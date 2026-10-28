<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\CardController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Models\BusinessCard;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardImageService;
use App\Services\CardRenderService;
use App\Services\CardService;
use App\Services\QrCodeService;
use chillerlan\QRCode\QRCode as ChillerlanDecoder;

class CardExportPngTest extends TestCase
{
    private CardImageService $imageService;
    private CardRepository $cardRepo;
    private AuthService $authService;
    private CardService $cardService;
    private CardController $cardController;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $userRepo = new UserRepository($pdo);
        $this->cardRepo = new CardRepository($pdo);
        $this->authService = new AuthService($pdo, $userRepo, $this->cardRepo);
        $this->imageService = new CardImageService($this->cardRepo);
        $this->cardService = new CardService($pdo, $this->cardRepo, cardImageService: $this->imageService);
        $qrService = new QrCodeService();
        $renderService = new CardRenderService($this->cardRepo, $qrService);
        $this->cardController = new CardController(
            $this->authService,
            $this->cardService,
            $this->cardRepo,
            $qrService,
            $renderService,
            $this->imageService
        );
    }

    /**
     * Test 1: Les fichiers PNG générés font exactement 1004 × 650 px (300 DPI).
     */
    public function testPngDimensionsAndResolution(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Jean',
            'last_name' => 'Valjean',
            'login_email' => 'valjean_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        // Generate front & back PNGs
        $frontPngPath = $this->imageService->getCardPng($card, 'front', true);
        $backPngPath = $this->imageService->getCardPng($card, 'back', true);

        $this->assertTrue(file_exists($frontPngPath), "Le fichier PNG recto doit exister.");
        $this->assertTrue(file_exists($backPngPath), "Le fichier PNG verso doit exister.");

        $frontInfo = getimagesize($frontPngPath);
        $backInfo = getimagesize($backPngPath);

        $this->assertEquals(1004, $frontInfo[0], "La largeur du recto doit être de 1004 px.");
        $this->assertEquals(650, $frontInfo[1], "La hauteur du recto doit être de 650 px.");

        $this->assertEquals(1004, $backInfo[0], "La largeur du verso doit être de 1004 px.");
        $this->assertEquals(650, $backInfo[1], "La hauteur du verso doit être de 650 px.");

        // Check 300 DPI pHYs chunk in PNG
        $rawFront = (string) file_get_contents($frontPngPath);
        $this->assertStringContains('pHYs', $rawFront, "Le fichier PNG doit intégrer le chunk pHYs pour 300 DPI.");
    }

    /**
     * Test 2: Le QR code sur le verso PNG est scannable et décode vers l'URL publique exacte.
     */
    public function testVersoPngContainsScannableQrCode(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Claire',
            'last_name' => 'Chazal',
            'login_email' => 'claire_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $backPngPath = $this->imageService->getCardPng($card, 'back', true);
        $this->assertTrue(file_exists($backPngPath));

        // Automatic QR decoding from PNG image
        $decoder = new ChillerlanDecoder();
        $result = $decoder->readFromFile($backPngPath);

        $expectedUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/') . '/c/' . $card->publicId;
        $this->assertEquals($expectedUrl, $result->data, "L'URL décodée à partir du PNG verso doit correspondre exactement à l'URL publique.");
    }

    /**
     * Test 3: Assainissement du nom de fichier pour Content-Disposition (prenom-nom-recto.png).
     */
    public function testDownloadFilenameSanitization(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Hélène',
            'last_name' => "d'Artois-Béthune",
            'login_email' => 'helene_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $frontName = $this->imageService->getDownloadFilename($card, 'front');
        $backName = $this->imageService->getDownloadFilename($card, 'back');

        $this->assertEquals('helene-d-artois-bethune-recto.png', $frontName);
        $this->assertEquals('helene-d-artois-bethune-verso.png', $backName);
    }

    /**
     * Test 4: Mise en cache non devinable et invalidation lors de la modification de la carte.
     */
    public function testCacheGenerationAndInvalidationOnCardUpdate(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Pierre',
            'last_name' => 'Curie',
            'login_email' => 'curie_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $cardId = (int) $card->id;

        // 1. Initial generation -> creates cache file
        $cachedPath = $this->imageService->getCardPng($card, 'front');
        $this->assertTrue(file_exists($cachedPath), "Le fichier de cache doit exister.");
        $this->assertStringContains("card_{$cardId}_front_", basename($cachedPath), "Le nom de fichier doit inclure le préfixe sécurisé.");

        // Filename has SHA-256 hash (64 hex characters)
        $this->assertTrue(strlen(basename($cachedPath)) >= 64, "Le nom de fichier de cache doit être non devinable.");

        // 2. Update card via CardService -> triggers cache invalidation
        $this->cardService->updateCard((int) $user->id, [
            'display_first_name' => 'Pierre-Marie',
            'display_last_name' => 'Curie',
            'job_title' => 'Prix Nobel de Physique',
        ]);

        // Assert old cache file was deleted
        $this->assertFalse(file_exists($cachedPath), "L'ancien fichier de cache doit avoir été supprimé lors de la mise à jour de la carte.");

        // 3. Re-requesting PNG creates a fresh file reflecting new updated_at
        $updatedCard = $this->cardRepo->findById($cardId);
        $newCachedPath = $this->imageService->getCardPng($updatedCard, 'front');
        $this->assertTrue(file_exists($newCachedPath), "Un nouveau fichier de cache doit être généré.");
    }

    /**
     * Test 5: Routes GET /card/download/front.png et back.png (Règle 9 & Content-Disposition).
     */
    public function testDownloadRoutesEnforceAuthenticationAndOwnership(): void
    {
        // 1. Unauthenticated -> 302 to /login
        $_SESSION = [];
        $unauthReq = new Request('GET', '/card/download/front.png');
        $unauthResp = $this->cardController->downloadFrontPng($unauthReq);
        $this->assertEquals(302, $unauthResp->getStatusCode(), "Un utilisateur non authentifié doit être redirigé vers /login.");

        // 2. Authenticated user -> 200 OK attachment
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'DownloadUser',
            'last_name' => 'Test',
            'login_email' => 'dl_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $_SESSION['user_id'] = (int) $user->id;

        // Front download
        $reqFront = new Request('GET', '/card/download/front.png');
        $respFront = $this->cardController->downloadFrontPng($reqFront);
        $this->assertEquals(200, $respFront->getStatusCode());
        $this->assertEquals('image/png', $respFront->getHeaders()['Content-Type'] ?? '');
        $this->assertStringContains('attachment; filename="downloaduser-test-recto.png"', $respFront->getHeaders()['Content-Disposition'] ?? '');
        $this->assertTrue(str_starts_with($respFront->getContent(), "\x89PNG\r\n\x1a\n"));

        // Back download
        $reqBack = new Request('GET', '/card/download/back.png');
        $respBack = $this->cardController->downloadBackPng($reqBack);
        $this->assertEquals(200, $respBack->getStatusCode());
        $this->assertEquals('image/png', $respBack->getHeaders()['Content-Type'] ?? '');
        $this->assertStringContains('attachment; filename="downloaduser-test-recto.png"', $respFront->getHeaders()['Content-Disposition'] ?? '');
        $this->assertStringContains('attachment; filename="downloaduser-test-verso.png"', $respBack->getHeaders()['Content-Disposition'] ?? '');
        $this->assertTrue(str_starts_with($respBack->getContent(), "\x89PNG\r\n\x1a\n"));

        $_SESSION = [];
    }

    /**
     * Test 6: Moteur de repli GD pur PHP (quand aucun navigateur headless n'est disponible).
     */
    public function testFallbackGdEngineWhenBrowserUnavailable(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Fallback',
            'last_name' => 'User',
            'login_email' => 'fallback_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        // Force browserPath to null to enforce GD fallback
        $gdImageService = new CardImageService(
            cardRepo: $this->cardRepo,
            browserPath: 'C:\\non\\existent\\browser.exe'
        );

        $gdFrontPath = $gdImageService->getCardPng($card, 'front', true);
        $gdBackPath = $gdImageService->getCardPng($card, 'back', true);

        $this->assertTrue(file_exists($gdFrontPath));
        $this->assertTrue(file_exists($gdBackPath));

        $frontInfo = getimagesize($gdFrontPath);
        $backInfo = getimagesize($gdBackPath);

        $this->assertEquals(1004, $frontInfo[0], "Le rendu GD recto doit faire 1004 px de large.");
        $this->assertEquals(650, $frontInfo[1], "Le rendu GD recto doit faire 650 px de haut.");

        $this->assertEquals(1004, $backInfo[0], "Le rendu GD verso doit faire 1004 px de large.");
        $this->assertEquals(650, $backInfo[1], "Le rendu GD verso doit faire 650 px de haut.");

        // Scannable QR code on verso even with GD fallback
        $decoder = new ChillerlanDecoder();
        $result = $decoder->readFromFile($gdBackPath);
        $expectedUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/') . '/c/' . $card->publicId;
        $this->assertEquals($expectedUrl, $result->data, "Le QR code généré via GD doit être parfaitement décodable.");
    }
}
