<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\PublicCardController;
use App\Core\Database;
use App\Core\Request;
use App\Models\PublicCardView;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\ContactExportService;
use App\Services\PublicCardService;

class ContactExportTest extends TestCase
{
    private ContactExportService $exportService;
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
        $this->exportService = new ContactExportService();
        $this->publicService = new PublicCardService($this->cardRepo);
        $this->controller = new PublicCardController($this->publicService, $this->exportService);
    }

    /**
     * Test 1: Caractères accentués préservés en UTF-8 et nom de fichier assaini.
     */
    public function testAccentedCharactersPreservedAndFilenameSanitized(): void
    {
        $cardView = new PublicCardView(
            publicId: 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
            displayName: 'Éléonore Cézanne',
            displayFirstName: 'Éléonore',
            displayLastName: 'Cézanne',
            companyName: 'Société Générale d’Électronique',
            jobTitle: 'Ingénieure en Chef',
            phoneDisplay: '+33 6 12 34 56 78',
            phoneTelUri: 'tel:+33612345678',
            email: 'eleonore.cezanne@sge.fr'
        );

        $vcard = $this->exportService->generateVCard($cardView);

        // UTF-8 preservation in vCard
        $this->assertStringContains('FN:Éléonore Cézanne', $vcard);
        $this->assertStringContains('N:Cézanne;Éléonore;;;', $vcard);
        $this->assertStringContains('ORG:Société Générale d’Électronique', $vcard);
        $this->assertStringContains('TITLE:Ingénieure en Chef', $vcard);

        // CRLF line endings check
        $this->assertStringContains("\r\n", $vcard);
        $this->assertTrue(str_ends_with($vcard, "\r\n"));

        // Sanitized filename
        $filename = $this->exportService->sanitizeFilename($cardView);
        $this->assertEquals('eleonore-cezanne.vcf', $filename);
    }

    /**
     * Test 2: Virgule et points-virgules dans le nom d'entreprise échappés correctement.
     */
    public function testCommasAndSemicolonsInCompanyAreEscaped(): void
    {
        $cardView = new PublicCardView(
            publicId: 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
            displayName: 'Marc Dupont',
            displayFirstName: 'Marc',
            displayLastName: 'Dupont',
            companyName: 'ScanV SAS, Solutions Numériques & Conseil; France',
            jobTitle: 'Directeur, R&D'
        );

        $vcard = $this->exportService->generateVCard($cardView);

        // Commas and semicolons must be preceded by backslash in text values
        $this->assertStringContains('ORG:ScanV SAS\, Solutions Numériques & Conseil\; France', $vcard);
        $this->assertStringContains('TITLE:Directeur\, R&D', $vcard);
    }

    /**
     * Test 3: Champ privé absent du fichier vCard (Règle 6).
     */
    public function testPrivateFieldIsAbsentFromVCard(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Agent',
            'last_name' => 'Secret',
            'login_email' => 'agent_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $secretPhone = '06 88 77 66 55';
        $secretEmail = 'classified_' . $unique . '@cia.gov';
        $secretAddress = 'Secret Underground Base, Room 101';

        $this->cardRepo->update((int) $card->id, [
            'professional_phone' => $secretPhone,
            'normalized_phone' => '+33688776655',
            'professional_email' => $secretEmail,
            'company_address' => $secretAddress,
            'job_title' => 'Consultant Public',
        ]);

        // Explicitly set phone, email, and address to HIDDEN (0)
        $this->cardRepo->updateVisibility((int) $card->id, [
            'show_job_title' => 1,
            'show_phone' => 0,           // PRIVATE
            'show_email' => 0,           // PRIVATE
            'show_company_address' => 0, // PRIVATE
        ]);

        $request = new Request('GET', '/c/' . $card->publicId . '/vcard');
        $response = $this->controller->vcard($request, $card->publicId);

        $this->assertEquals(200, $response->getStatusCode());
        $vcard = $response->getContent();

        // Visible fields
        $this->assertStringContains('FN:Agent Secret', $vcard);
        $this->assertStringContains('TITLE:Consultant Public', $vcard);

        // Private fields must be strictly absent
        $this->assertStringNotContains($secretPhone, $vcard);
        $this->assertStringNotContains('+33688776655', $vcard);
        $this->assertStringNotContains('TEL', $vcard);

        $this->assertStringNotContains($secretEmail, $vcard);
        $this->assertStringNotContains('EMAIL', $vcard);

        $this->assertStringNotContains($secretAddress, $vcard);
        $this->assertStringNotContains('ADR', $vcard);

        // Header check
        $headers = $response->getHeaders();
        $this->assertStringContains('text/vcard', $headers['Content-Type']);
        $this->assertStringContains('attachment', $headers['Content-Disposition']);
        $this->assertStringContains('agent-secret.vcf', $headers['Content-Disposition']);
    }

    /**
     * Test 4: Folding at 75 octets with CRLF followed by space.
     */
    public function testLongLinesAreFoldedAt75Bytes(): void
    {
        $longOrg = 'Entreprise Internationale de Haute Technologie avec une Dénomination Exceptionnellement Longue et Complexe pour Tests de Compatibilité vCard';
        $cardView = new PublicCardView(
            publicId: 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
            displayName: 'Jean Valjean',
            displayFirstName: 'Jean',
            displayLastName: 'Valjean',
            companyName: $longOrg
        );

        $vcard = $this->exportService->generateVCard($cardView);

        // Check that ORG is folded with \r\n followed by space
        $this->assertStringContains("\r\n ", $vcard);

        // Every individual line in the vCard must not exceed 75 bytes (excluding CRLF)
        $lines = explode("\r\n", rtrim($vcard, "\r\n"));
        foreach ($lines as $line) {
            $this->assertTrue(strlen($line) <= 75, "Line exceeds 75 octets: '{$line}' (" . strlen($line) . " bytes)");
        }
    }

    /**
     * Test 5: Deactivated card returns 410 without VCF content.
     */
    public function testDeactivatedCardReturnsUnavailableWithoutVCardContent(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->authService->register([
            'first_name' => 'Inactive',
            'last_name' => 'CardHolder',
            'login_email' => 'inactive_' . $unique . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $this->assertTrue($card !== null);

        $this->cardRepo->update((int) $card->id, [
            'is_active' => 0, // DEACTIVATED
        ]);

        $request = new Request('GET', '/c/' . $card->publicId . '/vcard');
        $response = $this->controller->vcard($request, $card->publicId);

        $this->assertEquals(410, $response->getStatusCode());
        $content = $response->getContent();

        // Must NOT contain VCF data
        $this->assertStringNotContains('BEGIN:VCARD', $content);
        $this->assertStringNotContains('END:VCARD', $content);
        $this->assertStringContains("Cette carte n'est pas disponible", $content);
    }
}
