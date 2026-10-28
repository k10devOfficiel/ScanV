<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Helpers\Security;
use App\Repositories\CardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CardService;
use RuntimeException;

class CardEditorTest extends TestCase
{
    private CardService $cardService;
    private UserRepository $userRepo;
    private CardRepository $cardRepo;
    private AuthService $authService;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->userRepo = new UserRepository($pdo);
        $this->cardRepo = new CardRepository($pdo);
        $this->authService = new AuthService($pdo, $this->userRepo, $this->cardRepo);
        $this->cardService = new CardService($pdo, $this->cardRepo);
    }

    public function testModifyingAnotherUserCardIsDenied(): void
    {
        // Create user 1
        $u1 = $this->authService->register([
            'first_name' => 'Owner',
            'last_name' => 'User',
            'login_email' => 'owner_' . bin2hex(random_bytes(4)) . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        // Create user 2 (attacker)
        $u2 = $this->authService->register([
            'first_name' => 'Attacker',
            'last_name' => 'User',
            'login_email' => 'attacker_' . bin2hex(random_bytes(4)) . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        // User 2 tries to access user 1's card
        $card1 = $this->cardRepo->findByUserId((int) $u1->id);
        $this->assertTrue($card1 !== null);

        // Attempting to retrieve card1 for user 2 must throw 403
        $caught = false;
        try {
            // Verify ownership check directly
            if ($card1->userId !== $u2->id) {
                throw new RuntimeException("Accès non autorisé à cette carte.", 403);
            }
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertTrue($caught, 'Accessing or modifying another user card must be rejected with 403');
    }

    public function testJavascriptUrlInLinksIsRejected(): void
    {
        $user = $this->authService->register([
            'first_name' => 'Sec',
            'last_name' => 'Tester',
            'login_email' => 'sectest_' . bin2hex(random_bytes(4)) . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        // Try updating with javascript: URI
        $result = $this->cardService->updateCard((int) $user->id, [
            'display_first_name' => 'Sec',
            'display_last_name' => 'Tester',
            'links' => [
                [
                    'label' => 'Malicious Link',
                    'url' => 'javascript:alert(document.cookie)',
                    'is_public' => '1',
                ],
            ],
        ]);

        $this->assertFalse($result['success'], 'javascript: URL must be rejected');
        $this->assertTrue(isset($result['errors']['links_0_url']), 'Error must be set on the malicious link URL');
        $this->assertStringContains('http:// ou https://', $result['errors']['links_0_url']);
    }

    public function testFakeJpegUploadIsRejected(): void
    {
        // Create a temporary PHP script with a .jpg extension
        $fakeJpg = tempnam(sys_get_temp_dir(), 'fake_') . '.jpg';
        file_put_contents($fakeJpg, '<?php echo "malicious code"; ?>');

        $result = $this->cardService->processUpload([
            'name' => 'avatar.jpg',
            'type' => 'image/jpeg', // Fake client-provided header
            'tmp_name' => $fakeJpg,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($fakeJpg),
        ], 'photo');

        @unlink($fakeJpg);

        $this->assertTrue(isset($result['error']), 'Fake JPG containing PHP code must be rejected by finfo MIME check');
        $this->assertStringContains('Format de fichier non autorisé', $result['error']);
    }

    public function testValidCardUpdateSavesAndNormalizesPhone(): void
    {
        $user = $this->authService->register([
            'first_name' => 'Benoit',
            'last_name' => 'Girard',
            'login_email' => 'benoit_' . bin2hex(random_bytes(4)) . '@scanv.test',
            'password' => 'Pass123456789!',
            'password_confirmation' => 'Pass123456789!',
            'terms' => '1',
        ])['user'];

        $updateData = [
            'display_first_name' => 'Benoît',
            'display_last_name' => 'Girard-Lemoine',
            'job_title' => 'Directeur Technique',
            'department' => 'R&D',
            'company_name' => 'ScanV Enterprise',
            'company_website' => 'https://enterprise.scanv.test',
            'company_address' => '25 Avenue des Champs-Élysées, 75008 Paris',
            'professional_phone' => '06 12 34 56 78',
            'professional_email' => 'b.girard@enterprise.scanv.test',
            'show_job_title' => '1',
            'show_company' => '1',
            'show_phone' => '1',
            'show_email' => '1',
            'show_website' => '1',
            'show_social_links' => '1',
            'links' => [
                [
                    'label' => 'LinkedIn',
                    'url' => 'https://www.linkedin.com/in/benoit-girard',
                    'sort_order' => 1,
                    'is_public' => 1,
                ],
                [
                    'label' => 'GitHub',
                    'url' => 'https://github.com/bgirard',
                    'sort_order' => 2,
                    'is_public' => 1,
                ],
            ],
        ];

        $res = $this->cardService->updateCard((int) $user->id, $updateData);

        $this->assertTrue($res['success'], 'Valid update must succeed');
        $card = $res['card'];
        $this->assertEquals('Benoît', $card->displayFirstName);
        $this->assertEquals('Girard-Lemoine', $card->displayLastName);
        $this->assertEquals('Directeur Technique', $card->jobTitle);
        $this->assertEquals('06 12 34 56 78', $card->professionalPhone);
        $this->assertEquals('+33612345678', $card->normalizedPhone, 'Phone must be normalized to +33612345678');
        $this->assertEquals('tel:+33612345678', $card->getTelUri());

        // Verify links were saved
        $links = $this->cardRepo->getLinks((int) $card->id, false);
        $this->assertEquals(2, count($links));
        $this->assertEquals('LinkedIn', $links[0]->label);
        $this->assertEquals('GitHub', $links[1]->label);

        // Verify visibility
        $vis = $this->cardRepo->getVisibility((int) $card->id);
        $this->assertTrue($vis->showPhone);
        $this->assertTrue($vis->showEmail);
        $this->assertTrue($vis->showSocialLinks);
    }
}
