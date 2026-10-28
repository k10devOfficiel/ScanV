<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\CardController;
use App\Core\Database;
use App\Core\Request;
use App\Models\BusinessCard;
use App\Models\CardVisibility;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardRenderService;
use App\Services\CardService;
use App\Services\QrCodeService;

class CardRenderTest extends TestCase
{
    private CardRenderService $renderService;
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
        $this->cardService = new CardService($pdo, $this->cardRepo);
        $qrService = new QrCodeService();
        $this->renderService = new CardRenderService($this->cardRepo, $qrService);
        $this->cardController = new CardController(
            $this->authService,
            $this->cardService,
            $this->cardRepo,
            $qrService,
            $this->renderService
        );
    }

    /**
     * Jeu de données 1 : Carte complète (tous les champs renseignés et visibles).
     */
    public function testRenderCompleteCardFrontAndBack(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Sophie',
            'last_name' => 'Laurent',
            'login_email' => 'sophie_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        // Update with full card data
        $this->cardRepo->update((int) $card->id, [
            'display_first_name' => 'Sophie',
            'display_last_name' => 'Laurent',
            'job_title' => 'Directrice Générale & Stratégie',
            'department' => 'Comité Exécutif',
            'company_name' => 'InnovTech Solutions SAS',
            'company_website' => 'https://innovtech.example.com',
            'company_address' => '42 Avenue des Champs-Élysées, 75008 Paris',
            'professional_phone' => '+33 1 42 68 55 00',
            'normalized_phone' => '+33142685500',
            'professional_email' => 'sophie.laurent@innovtech.example.com',
            'theme_key' => 'default',
            'is_active' => 1,
        ]);

        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_photo' => 1,
            'show_job_title' => 1,
            'show_department' => 1,
            'show_company' => 1,
            'show_company_address' => 1,
            'show_phone' => 1,
            'show_email' => 1,
            'show_website' => 1,
            'show_social_links' => 1,
        ]);

        $this->cardRepo->syncLinks((int) $card->id, [
            [
                'label' => 'LinkedIn',
                'url' => 'https://linkedin.com/in/sophie-laurent',
                'sort_order' => 0,
                'is_public' => 1,
            ],
        ]);

        $updatedCard = $this->cardRepo->findById((int) $card->id);

        // 1. Rendu RECTO
        $frontHtml = $this->renderService->renderFront($updatedCard);

        $this->assertStringContains('Sophie Laurent', $frontHtml, "Le nom doit figurer sur le recto.");
        $this->assertStringContains('Directrice Générale &amp; Stratégie', $frontHtml, "La fonction doit figurer sur le recto.");
        $this->assertStringContains('Comité Exécutif', $frontHtml, "Le département doit figurer sur le recto.");
        $this->assertStringContains('InnovTech Solutions SAS', $frontHtml, "Le nom de l'entreprise doit figurer sur le recto.");
        $this->assertStringContains('+33 1 42 68 55 00', $frontHtml, "Le téléphone doit figurer sur le recto.");
        $this->assertStringContains('sophie.laurent@innovtech.example.com', $frontHtml, "L'email doit figurer sur le recto.");
        $this->assertStringContains('https://innovtech.example.com', $frontHtml, "Le site web doit figurer sur le recto.");
        $this->assertStringContains('42 Avenue des Champs-Élysées', $frontHtml, "L'adresse doit figurer sur le recto.");
        $this->assertStringContains('LinkedIn', $frontHtml, "Le lien public doit figurer sur le recto.");
        $this->assertStringContains('scanv-card scanv-card-front', $frontHtml, "La classe du gabarit recto doit être présente.");
        $this->assertStringContains('<!-- DESIGN SLOT: RECTO_BEFORE_CARD -->', $frontHtml, "Les slots de personnalisation doivent être présents.");

        // 2. Rendu VERSO
        $backHtml = $this->renderService->renderBack($updatedCard);

        $this->assertStringContains('scanv-card scanv-card-back', $backHtml, "La classe du gabarit verso doit être présente.");
        $this->assertStringContains('scanv-back-qr-box', $backHtml, "La boîte QR code avec quiet zone doit être présente.");
        $this->assertStringContains('Scannez pour enregistrer mon contact', $backHtml, "Le texte obligatoire doit figurer sur le verso.");
        $this->assertStringContains('<svg', $backHtml, "Le QR code SVG doit être intégré sur le verso.");
        $this->assertStringContains('<!-- DESIGN SLOT: VERSO_BEFORE_CARD -->', $backHtml, "Les slots de personnalisation verso doivent être présents.");
    }

    /**
     * Jeu de données 2 : Carte minimale (champs obligatoires uniquement, optionnels vides/masqués).
     */
    public function testRenderMinimalCardFrontAndBack(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Marc',
            'last_name' => 'Dubois',
            'login_email' => 'marc_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        // Keep card minimal (no optional fields)
        $this->cardRepo->update((int) $card->id, [
            'display_first_name' => 'Marc',
            'display_last_name' => 'Dubois',
            'job_title' => null,
            'department' => null,
            'company_name' => null,
            'company_website' => null,
            'company_address' => null,
            'professional_phone' => null,
            'normalized_phone' => null,
            'professional_email' => null,
            'photo_path' => null,
            'logo_path' => null,
        ]);

        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_photo' => 0,
            'show_job_title' => 0,
            'show_department' => 0,
            'show_company' => 0,
            'show_company_address' => 0,
            'show_phone' => 0,
            'show_email' => 0,
            'show_website' => 0,
            'show_social_links' => 0,
        ]);

        $minimalCard = $this->cardRepo->findById((int) $card->id);

        // 1. Rendu RECTO
        $frontHtml = $this->renderService->renderFront($minimalCard);

        $this->assertStringContains('Marc Dubois', $frontHtml, "Le nom doit figurer.");
        $this->assertStringContains('no-photo', $frontHtml, "La classe no-photo doit être appliquée si pas de photo.");
        // Assure que les champs masqués ne sont JAMAIS rendus (Règle 6)
        $this->assertStringNotContains('scanv-front-avatar-container', $frontHtml, "Aucun conteneur d'avatar si photo absente.");
        $this->assertStringNotContains('scanv-front-role', $frontHtml, "Aucun titre de poste si masqué.");
        $this->assertStringNotContains('scanv-front-company', $frontHtml, "Aucune entreprise si masquée.");
        $this->assertStringNotContains('Tél :', $frontHtml, "Aucun téléphone si masqué.");
        $this->assertStringNotContains('Email :', $frontHtml, "Aucun e-mail si masqué.");
        $this->assertStringNotContains('Web :', $frontHtml, "Aucun site si masqué.");

        // 2. Rendu VERSO
        $backHtml = $this->renderService->renderBack($minimalCard);
        $this->assertStringContains('Scannez pour enregistrer mon contact', $backHtml);
        $this->assertStringContains('scanv-back-qr-box', $backHtml);
    }

    /**
     * Jeu de données 3 : Textes très longs (noms à rallonge, fonctions étendues, anti-débordement).
     */
    public function testRenderVeryLongTextAndNamesSafelyTruncated(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Jean-Sébastien-Alexandre',
            'last_name' => 'Maximilien-Barthélémy de la Tour-du-Pin',
            'login_email' => 'long_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $veryLongTitle = str_repeat('Directeur Général Délégué aux Affaires Stratégiques Internationales ', 2);
        $veryLongCompany = str_repeat('Compagnie Générale Transatlantique et Aérospatiale Internationale ', 2);
        $veryLongAddress = '1234567890 Boulevard des Très Grandes Organisations Internationales et Diplomatiques, Bâtiment C, Étage 14, Bureau 402, 75008 Paris Cedex 08, France';

        $this->cardRepo->update((int) $card->id, [
            'display_first_name' => 'Jean-Sébastien-Alexandre',
            'display_last_name' => 'Maximilien-Barthélémy de la Tour-du-Pin',
            'job_title' => mb_substr($veryLongTitle, 0, 150),
            'department' => 'Département Global',
            'company_name' => mb_substr($veryLongCompany, 0, 150),
            'company_website' => 'https://tres-long-nom-de-domaine-organisationnel-international.example.com',
            'company_address' => $veryLongAddress,
            'professional_phone' => '+33 (0)1 23 45 67 89 01 23',
            'professional_email' => 'jean-sebastien-alexandre.maximilien-barthelemy@organisation-internationale.example.com',
        ]);

        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_company' => 1,
            'show_company_address' => 1,
            'show_job_title' => 1,
            'show_phone' => 1,
            'show_email' => 1,
            'show_website' => 1,
        ]);

        $longCard = $this->cardRepo->findById((int) $card->id);

        $frontHtml = $this->renderService->renderFront($longCard);

        // Verify HTML escaping and truncation classes
        $this->assertStringContains('truncate', $frontHtml, "Les classes de troncature CSS doivent être appliquées.");
        $this->assertStringContains('clamp-2', $frontHtml, "Les classes de limitation multi-lignes doivent être appliquées.");
        $this->assertStringContains('Jean-Sébastien-Alexandre Maximilien-Barthélémy de la Tour-du-Pin', $frontHtml);
        $this->assertStringContains('scanv-card scanv-card-front', $frontHtml);
    }

    /**
     * Test 4 : Routes privées GET /card/preview/front et GET /card/preview/back (Règle 9).
     */
    public function testPreviewRoutesEnforceAuthenticationAndOwnership(): void
    {
        // 1. Non authentifié -> redirection 302 vers /login
        $_SESSION = [];
        $unauthReq = new Request('GET', '/card/preview/front');
        $unauthResp = $this->cardController->previewFront($unauthReq);
        $this->assertEquals(302, $unauthResp->getStatusCode(), "Un visiteur non connecté doit être redirigé vers /login.");

        // 2. Utilisateur authentifié -> 200 OK
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'PreviewUser',
            'last_name' => 'Test',
            'login_email' => 'preview_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $_SESSION['user_id'] = (int) $user->id;

        // Front preview
        $reqFront = new Request('GET', '/card/preview/front');
        $respFront = $this->cardController->previewFront($reqFront);
        $this->assertEquals(200, $respFront->getStatusCode(), "La route GET /card/preview/front doit renvoyer 200 OK.");
        $this->assertStringContains('text/html', $respFront->getHeaders()['Content-Type'] ?? '', "Le Content-Type doit être text/html.");
        $this->assertStringContains('scanv-card-front', $respFront->getContent());

        // Back preview
        $reqBack = new Request('GET', '/card/preview/back');
        $respBack = $this->cardController->previewBack($reqBack);
        $this->assertEquals(200, $respBack->getStatusCode(), "La route GET /card/preview/back doit renvoyer 200 OK.");
        $this->assertStringContains('text/html', $respBack->getHeaders()['Content-Type'] ?? '', "Le Content-Type doit être text/html.");
        $this->assertStringContains('scanv-card-back', $respBack->getContent());
        $this->assertStringContains('Scannez pour enregistrer mon contact', $respBack->getContent());

        $_SESSION = [];
    }

    /**
     * Test 5 : Conformité typographique locale (Aucun CDN, variables CSS 85x55mm 300dpi).
     */
    public function testLocalFontsAndNoCdnDependencies(): void
    {
        $root = dirname(__DIR__);
        $fontPath = $root . '/public/assets/fonts/OpenSans-Regular.ttf';
        $this->assertTrue(file_exists($fontPath), "Le fichier de police locale OpenSans-Regular.ttf doit exister.");

        $cssPath = $root . '/public/assets/css/card-default.css';
        $this->assertTrue(file_exists($cssPath), "La feuille de style card-default.css doit exister.");

        $cssContent = (string) file_get_contents($cssPath);

        // Verification of 85x55 mm @ 300 dpi dimensions
        $this->assertStringContains('--card-width: 1004px;', $cssContent, "La variable --card-width doit être 1004px (85 mm à 300 dpi).");
        $this->assertStringContains('--card-height: 650px;', $cssContent, "La variable --card-height doit être 650px (55 mm à 300 dpi).");
        $this->assertStringContains('--card-safety-margin: 35px;', $cssContent, "La variable --card-safety-margin doit correspondre à 3 mm (~35 px).");

        // Strict rejection of external CDN references (fonts.googleapis, cdnjs, etc.)
        $this->assertStringNotContains('fonts.googleapis.com', $cssContent, "Aucune référence à un CDN Google Fonts n'est autorisée.");
        $this->assertStringNotContains('cdnjs.cloudflare.com', $cssContent, "Aucune référence à un CDN externe n'est autorisée.");
        $this->assertStringNotContains('cdn.jsdelivr.net', $cssContent, "Aucune référence à un CDN externe n'est autorisée.");
    }
}
