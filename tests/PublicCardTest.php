<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\PublicCardController;
use App\Core\Database;
use App\Core\Request;
use App\Models\BusinessCard;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\PublicCardService;

class PublicCardTest extends TestCase
{
    private PublicCardService $publicService;
    private PublicCardController $controller;
    private CardRepository $cardRepo;
    private AuthService $authService;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $userRepo = new UserRepository($pdo);
        $this->cardRepo = new CardRepository($pdo);
        $this->authService = new AuthService($pdo, $userRepo, $this->cardRepo);
        $this->publicService = new PublicCardService($this->cardRepo);
        $this->controller = new PublicCardController($this->publicService);
    }

    /**
     * Test 1: Hidden fields are completely absent from HTML and data attributes.
     */
    public function testHiddenFieldIsAbsentFromHtml(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'VisibleFirst',
            'last_name' => 'VisibleLast',
            'login_email' => 'vis_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $secretPhone = '06 99 88 77 66';
        $secretEmail = 'confidential_' . $unique . '@secret.com';
        $secretAddress = 'Secret Bunker 42';

        $this->cardRepo->update((int) $card->id, [
            'professional_phone' => $secretPhone,
            'normalized_phone' => '+33699887766',
            'professional_email' => $secretEmail,
            'company_address' => $secretAddress,
        ]);

        // Explicitly set phone, email, address to hidden (0)
        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_photo' => 0,
            'show_job_title' => 1,
            'show_department' => 0,
            'show_company' => 1,
            'show_company_address' => 0, // HIDDEN
            'show_phone' => 0,           // HIDDEN
            'show_email' => 0,           // HIDDEN
            'show_website' => 0,
            'show_social_links' => 0,
        ]);

        $request = new Request('GET', '/c/' . $card->publicId);
        $response = $this->controller->show($request, $card->publicId);

        $this->assertEquals(200, $response->getStatusCode());
        $html = $response->getContent();

        // Visible fields
        $this->assertStringContains('VisibleFirst VisibleLast', $html);

        // Hidden fields must NOT exist anywhere in HTML (Règle 6)
        $this->assertStringNotContains($secretPhone, $html);
        $this->assertStringNotContains('+33699887766', $html);
        $this->assertStringNotContains('tel:', $html);
        $this->assertStringNotContains($secretEmail, $html);
        $this->assertStringNotContains('mailto:', $html);
        $this->assertStringNotContains($secretAddress, $html);

        // Nor in data-* attributes
        $this->assertStringNotContains('data-phone', $html);
        $this->assertStringNotContains('data-email', $html);
    }

    /**
     * Test 2: Updating phone number is immediately visible with same public_id.
     */
    public function testUpdatingPhoneNumberReflectsImmediatelyWithSamePublicId(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Speedy',
            'last_name' => 'Gonzales',
            'login_email' => 'speedy_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        // Initial phone
        $this->cardRepo->update((int) $card->id, [
            'professional_phone' => '06 11 22 33 44',
            'normalized_phone' => '+33611223344',
        ]);
        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_phone' => 1,
            'show_job_title' => 1,
            'show_company' => 1,
        ]);

        $request1 = new Request('GET', '/c/' . $card->publicId);
        $response1 = $this->controller->show($request1, $card->publicId);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertStringContains('06 11 22 33 44', $response1->getContent());
        $this->assertStringContains('tel:+33611223344', $response1->getContent());

        // Update phone with same public_id
        $this->cardRepo->update((int) $card->id, [
            'professional_phone' => '07 55 66 77 88',
            'normalized_phone' => '+33755667788',
        ]);

        $request2 = new Request('GET', '/c/' . $card->publicId);
        $response2 = $this->controller->show($request2, $card->publicId);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertStringContains('07 55 66 77 88', $response2->getContent());
        $this->assertStringContains('tel:+33755667788', $response2->getContent());
        $this->assertStringNotContains('06 11 22 33 44', $response2->getContent());

        // Cache control check
        $headers = $response2->getHeaders();
        $this->assertTrue(isset($headers['Cache-Control']));
        $this->assertStringContains('no-cache', $headers['Cache-Control']);
    }

    /**
     * Test 3: Deactivated card returns neutral unavailable page without any personal data.
     */
    public function testDeactivatedCardReturnsUnavailableWithoutAnyPersonalData(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Deactivated',
            'last_name' => 'UserIdentity',
            'login_email' => 'deact_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $this->cardRepo->update((int) $card->id, [
            'job_title' => 'Top Secret Agent',
            'company_name' => 'Confidential Corp',
            'professional_phone' => '06 00 00 00 00',
            'professional_email' => 'agent@confidential.com',
            'is_active' => 0, // DEACTIVATED
        ]);

        $request = new Request('GET', '/c/' . $card->publicId);
        $response = $this->controller->show($request, $card->publicId);

        // Deactivated status: 410 Gone neutral page
        $this->assertEquals(410, $response->getStatusCode());
        $html = $response->getContent();

        $this->assertStringContains("Cette carte n'est pas disponible", $html);
        $this->assertStringContains("Carte indisponible", $html);

        // Absolutely NO personal data leaked
        $this->assertStringNotContains('Deactivated', $html);
        $this->assertStringNotContains('UserIdentity', $html);
        $this->assertStringNotContains('Top Secret Agent', $html);
        $this->assertStringNotContains('Confidential Corp', $html);
        $this->assertStringNotContains('06 00 00 00 00', $html);
        $this->assertStringNotContains('agent@confidential.com', $html);
    }

    /**
     * Test 4: Unknown public_id returns 404 neutral unavailable page without leaking technical info.
     */
    public function testUnknownPublicIdReturns404Unavailable(): void
    {
        $nonExistentId = '11112222333344445555666677778888';
        $request = new Request('GET', '/c/' . $nonExistentId);
        $response = $this->controller->show($request, $nonExistentId);

        $this->assertEquals(404, $response->getStatusCode());
        $html = $response->getContent();

        $this->assertStringContains("Cette carte n'est pas disponible", $html);
        $this->assertStringContains("Carte indisponible", $html);
    }

    /**
     * Test 5: External links only show public ones with rel="noopener noreferrer" and target="_blank".
     */
    public function testExternalLinksOnlyDisplayPublicWithSecurityAttributes(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Link',
            'last_name' => 'Tester',
            'login_email' => 'link_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $this->cardRepo->syncLinks((int) $card->id, [
            [
                'label' => 'Mon GitHub Public',
                'url' => 'https://github.com/scanv-public',
                'is_public' => 1,
                'sort_order' => 1,
            ],
            [
                'label' => 'Mon Intranet Secret',
                'url' => 'https://intranet.private',
                'is_public' => 0, // PRIVATE
                'sort_order' => 2,
            ],
        ]);

        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_social_links' => 1,
        ]);

        $request = new Request('GET', '/c/' . $card->publicId);
        $response = $this->controller->show($request, $card->publicId);

        $this->assertEquals(200, $response->getStatusCode());
        $html = $response->getContent();

        $this->assertStringContains('Mon GitHub Public', $html);
        $this->assertStringContains('https://github.com/scanv-public', $html);
        $this->assertStringContains('target="_blank"', $html);
        $this->assertStringContains('rel="noopener noreferrer"', $html);

        // Private link must be absent
        $this->assertStringNotContains('Mon Intranet Secret', $html);
        $this->assertStringNotContains('https://intranet.private', $html);

        // Save contact button must point to vCard URL
        $this->assertStringContains('/c/' . $card->publicId . '/vcard', $html);
        $this->assertStringContains('Enregistrer le contact', $html);
    }
}
