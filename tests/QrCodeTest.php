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
use App\Services\CardService;
use App\Services\QrCodeService;
use chillerlan\QRCode\QRCode as ChillerlanDecoder;
use InvalidArgumentException;

class QrCodeTest extends TestCase
{
    private QrCodeService $qrService;
    private AuthService $authService;
    private CardService $cardService;
    private CardRepository $cardRepo;
    private CardController $cardController;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $userRepo = new UserRepository($pdo);
        $this->cardRepo = new CardRepository($pdo);
        $this->authService = new AuthService($pdo, $userRepo, $this->cardRepo);
        $this->cardService = new CardService($pdo, $this->cardRepo);
        $this->qrService = new QrCodeService();
        $this->cardController = new CardController($this->authService, $this->cardService, $this->cardRepo, $this->qrService);
    }

    /**
     * Test 1: Entry is strictly the complete public URL (APP_URL + /c/{public_id})
     * and invalid public IDs are rejected (Rule 5 & 7).
     */
    public function testEncodedUrlIsStrictlyPublicUrlWithNoPersonalData(): void
    {
        $appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
        $publicId = '0123456789abcdef0123456789abcdef';

        $builtUrl = $this->qrService->buildPublicUrl($publicId);
        $expectedUrl = $appUrl . '/c/' . $publicId;

        $this->assertEquals($expectedUrl, $builtUrl, "L'URL construite doit correspondre à APP_URL + /c/{public_id}");

        // Rejection of invalid public_ids
        $invalidIds = [
            '',
            '123',
            'xyz123',
            '0123456789abcdef0123456789abcde', // 31 chars
            '0123456789abcdef0123456789abcdef0', // 33 chars
            '0123456789abcdef0123456789abcdeg', // 'g' is non-hex
            '../sensitive/file',
        ];

        foreach ($invalidIds as $invalidId) {
            $threw = false;
            try {
                $this->qrService->buildPublicUrl($invalidId);
            } catch (InvalidArgumentException) {
                $threw = true;
            }
            $this->assertTrue($threw, "Un public_id invalide ('{$invalidId}') doit déclencher une InvalidArgumentException.");
        }
    }

    /**
     * Test 2: The QR code NEVER changes as long as public_id does not change.
     * The encoded URL and generated binary remain strictly identical after modifying card data.
     */
    public function testQrCodeUrlRemainsIdenticalAfterCardDataUpdate(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'InitialFirst',
            'last_name' => 'InitialLast',
            'login_email' => 'qr_stable_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null, "La carte doit être créée lors de l'inscription.");

        $initialPublicId = $card->publicId;
        $initialUrl = $this->qrService->buildPublicUrl($initialPublicId);
        $initialPng = $this->qrService->generatePng($initialPublicId, 300, 10, 'M');

        // Modify card data (change identity, phone, company, address, visibility)
        $updateResult = $this->cardService->updateCard((int) $user->id, [
            'display_first_name' => 'CompletelyNewFirst',
            'display_last_name' => 'CompletelyNewLast',
            'job_title' => 'Chief Technology Officer',
            'department' => 'R&D Direction',
            'company_name' => 'Cyber Corp Worldwide',
            'company_website' => 'https://cybercorp.example.com',
            'company_address' => '99 Innovation Boulevard, Paris',
            'professional_phone' => '+33699887766',
            'professional_email' => 'contact@cybercorp.example.com',
            'show_photo' => 0,
            'show_phone' => 1,
            'show_email' => 1,
            'show_company' => 1,
        ]);

        $this->assertTrue($updateResult['success'], "La mise à jour de la carte doit réussir.");

        $updatedCard = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertEquals($initialPublicId, $updatedCard->publicId, "Le public_id doit rester strictement invariable.");

        $updatedUrl = $this->qrService->buildPublicUrl($updatedCard->publicId);
        $updatedPng = $this->qrService->generatePng($updatedCard->publicId, 300, 10, 'M');

        // Check URL equality
        $this->assertEquals($initialUrl, $updatedUrl, "L'URL encodée dans le QR code doit rester strictement identique.");

        // Check exact binary equality of generated PNG
        $this->assertEquals($initialPng, $updatedPng, "Le flux binaire du QR code généré doit être 100% identique.");
    }

    /**
     * Test 3: Decodes the generated PNG and confirms that the encoded URL is correct.
     */
    public function testDecodesGeneratedPngAndConfirmsUrlIsCorrect(): void
    {
        $appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
        $publicId = 'abcdef0123456789abcdef0123456789';
        $expectedUrl = $appUrl . '/c/' . $publicId;

        // 1. Test with default error correction (M)
        $pngDataM = $this->qrService->generatePng($publicId, 300, 10, 'M');
        $this->assertTrue(strlen($pngDataM) > 0, "Le PNG généré ne doit pas être vide.");

        $decoderM = new ChillerlanDecoder();
        $resultM = $decoderM->readFromBlob($pngDataM);
        $this->assertEquals($expectedUrl, $resultM->data, "L'URL décodée depuis le PNG (M) doit correspondre fidèlement à l'URL attendue.");

        // 2. Test with Quartile error correction (Q)
        $pngDataQ = $this->qrService->generatePng($publicId, 350, 15, 'Q');
        $this->assertTrue(strlen($pngDataQ) > 0, "Le PNG généré (Q) ne doit pas être vide.");

        $decoderQ = new ChillerlanDecoder();
        $resultQ = $decoderQ->readFromBlob($pngDataQ);
        $this->assertEquals($expectedUrl, $resultQ->data, "L'URL décodée depuis le PNG (Q) doit correspondre fidèlement à l'URL attendue.");
    }

    /**
     * Test 4: PNG and SVG generation with configurable size, margin, and error correction (M/Q).
     */
    public function testConfigurableOutputsAndErrorCorrectionLevels(): void
    {
        $publicId = 'fedcba9876543210fedcba9876543210';

        // PNG Magic Number: \x89 P N G \r \n \x1a \n
        $png = $this->qrService->generatePng($publicId, 250, 5, 'M');
        $this->assertTrue(str_starts_with($png, "\x89PNG\r\n\x1a\n"), "Le fichier PNG doit avoir la signature binaire PNG officielle.");

        // SVG Validation
        $svg = $this->qrService->generateSvg($publicId, 250, 5, 'Q');
        $this->assertStringContains('<svg', $svg, "Le SVG doit contenir la balise racine <svg>.");
        $this->assertStringContains('viewBox=', $svg, "Le SVG doit contenir l'attribut viewBox.");
        $this->assertStringContains('xmlns="http://www.w3.org/2000/svg"', $svg, "Le SVG doit inclure le namespace XML du SVG.");

        // Error correction levels acceptance
        $qrMedium = $this->qrService->generatePng($publicId, 200, 5, 'm');
        $qrQuartile = $this->qrService->generatePng($publicId, 200, 5, 'q');
        $this->assertTrue(strlen($qrMedium) > 0);
        $this->assertTrue(strlen($qrQuartile) > 0);

        // Invalid error correction level rejection
        $invalidLevels = ['L', 'H', 'low', 'high', 'Z', 'invalid'];
        foreach ($invalidLevels as $invalidLevel) {
            $threw = false;
            try {
                $this->qrService->generatePng($publicId, 200, 5, $invalidLevel);
            } catch (InvalidArgumentException) {
                $threw = true;
            }
            $this->assertTrue($threw, "Le niveau de correction '{$invalidLevel}' doit être rejeté (seuls M et Q sont permis).");
        }
    }

    /**
     * Test 5: Private route GET /card/qr.png verifies authentication and card ownership (Rule 9).
     */
    public function testPrivateRouteQrPngEnforcesAuthenticationAndOwnership(): void
    {
        // 1. Unauthenticated request -> redirects to /login (302)
        $_SESSION = [];
        $unauthRequest = new Request('GET', '/card/qr.png');
        $unauthResponse = $this->cardController->qrCodePng($unauthRequest);
        $this->assertEquals(302, $unauthResponse->getStatusCode(), "Un utilisateur non connecté doit être redirigé vers /login.");

        // 2. Authenticated user with their own card -> serves 200 image/png
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'OwnerFirst',
            'last_name' => 'OwnerLast',
            'login_email' => 'qr_owner_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        // Simulate session login
        $_SESSION['user_id'] = (int) $user->id;

        $authRequest = new Request('GET', '/card/qr.png', [], [], ['size' => '320', 'margin' => '12', 'ec' => 'M']);
        $authResponse = $this->cardController->qrCodePng($authRequest);

        $this->assertEquals(200, $authResponse->getStatusCode(), "La route privée /card/qr.png doit retourner HTTP 200 pour le propriétaire.");
        $headers = $authResponse->getHeaders();
        $this->assertEquals('image/png', $headers['Content-Type'] ?? '', "Le Content-Type doit être image/png.");
        $this->assertTrue(str_starts_with($authResponse->getContent(), "\x89PNG\r\n\x1a\n"), "Le contenu retourné doit être un PNG valide.");

        // Decode served PNG from controller response to verify encoded URL
        $servedDecoder = new ChillerlanDecoder();
        $servedResult = $servedDecoder->readFromBlob($authResponse->getContent());
        $card = $this->cardRepo->findByUserId((int) $user->id);
        $expectedUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/') . '/c/' . $card->publicId;
        $this->assertEquals($expectedUrl, $servedResult->data, "L'image PNG servie par /card/qr.png doit décoder vers l'URL de la carte du propriétaire.");

        // 3. Authenticated user without card -> 404
        $user2 = $this->authService->register([
            'first_name' => 'NoCardUser',
            'last_name' => 'Test',
            'login_email' => 'no_card_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card2 = $this->cardRepo->findByUserId((int) $user2->id);
        if ($card2 !== null) {
            Database::getConnection()->exec("DELETE FROM business_cards WHERE id = " . (int) $card2->id);
        }

        $_SESSION['user_id'] = (int) $user2->id;
        $noCardRequest = new Request('GET', '/card/qr.png');
        $noCardResponse = $this->cardController->qrCodePng($noCardRequest);
        $this->assertEquals(404, $noCardResponse->getStatusCode(), "Un utilisateur authentifié sans carte doit recevoir une erreur 404.");

        // Clean session
        $_SESSION = [];
    }
}
