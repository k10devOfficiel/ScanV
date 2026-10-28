<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Models\BusinessCard;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardRenderService;
use App\Services\CardService;
use App\Services\CardThemeService;
use App\Services\QrCodeService;
use App\Services\CardPdfService;

class CardThemeTest extends TestCase
{
    private CardRepository $cardRepo;
    private UserRepository $userRepo;
    private AuthService $authService;
    private CardService $cardService;
    private CardRenderService $renderService;
    private CardPdfService $pdfService;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->userRepo = new UserRepository($pdo);
        $this->cardRepo = new CardRepository($pdo);
        $this->authService = new AuthService($pdo, $this->userRepo, $this->cardRepo);
        $this->cardService = new CardService($pdo, $this->cardRepo);
        $qrService = new QrCodeService();
        $this->renderService = new CardRenderService($this->cardRepo, $qrService);
        $this->pdfService = new CardPdfService($this->cardRepo, $qrService);
    }

    public function testTenThemesAvailableAndComplete(): void
    {
        $themes = CardThemeService::getAllThemes();
        $this->assertEquals(10, count($themes));

        $expectedKeys = [
            'default',
            'prestige_gold',
            'emerald_lagoon',
            'ocean_tech',
            'sunset_orange',
            'burgundy_luxe',
            'slate_minimal',
            'royal_violet',
            'warm_caramel',
            'cobalt_silver',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertTrue(CardThemeService::isValid($key), "Le thème {$key} doit être valide.");
            $t = CardThemeService::getTheme($key);
            $this->assertEquals($key, $t['key']);
            $this->assertNotEmpty($t['name']);
            $this->assertNotEmpty($t['primary']);
            $this->assertNotEmpty($t['rectoBg']);
            $this->assertNotEmpty($t['versoBg']);
        }
    }

    public function testUnknownThemeFallsBackToDefault(): void
    {
        $this->assertEquals('default', CardThemeService::normalize('unknown_theme_xyz'));
        $this->assertEquals('default', CardThemeService::normalize(''));
        $this->assertEquals('default', CardThemeService::normalize(null));

        $t = CardThemeService::getTheme('invalid_random');
        $this->assertEquals('default', $t['key']);
    }

    public function testCardUpdatePersistsThemeKey(): void
    {
        $reg = $this->authService->register([
            'first_name' => 'Ablan',
            'last_name' => 'Koffi',
            'login_email' => 'themetest_' . bin2hex(random_bytes(4)) . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ]);
        $user = $reg['user'];
        $userId = (int) $user->id;

        $card = $this->cardRepo->findByUserId($userId);
        $this->assertNotNull($card);
        $this->assertEquals('default', $card->themeKey);

        // Update card to 'prestige_gold'
        $res = $this->cardService->updateCard($userId, [
            'display_first_name' => 'Ablan',
            'display_last_name' => 'Koffi',
            'theme_key' => 'prestige_gold',
        ]);

        $this->assertTrue($res['success']);

        $updatedCard = $this->cardRepo->findById((int) $card->id);
        $this->assertNotNull($updatedCard);
        $this->assertEquals('prestige_gold', $updatedCard->themeKey);

        // Render Front with prestige_gold
        $htmlFront = $this->renderService->renderFront($updatedCard, null, true, false);
        $this->assertStringContainsString('card-prestige_gold.css', $htmlFront);

        // Render Back with prestige_gold
        $htmlBack = $this->renderService->renderBack($updatedCard, null, true, false);
        $this->assertStringContainsString('card-prestige_gold.css', $htmlBack);

        // Render PDF HTML with prestige_gold
        $pdfHtml = $this->pdfService->renderHtml($updatedCard);
        $this->assertStringContainsString('#0F172A', $pdfHtml); // prestige_gold recto bg
        $this->assertStringContainsString('#D97706', $pdfHtml); // prestige_gold primary color
    }
}
