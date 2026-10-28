<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Core\Env;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\CardVisibility;
use App\Models\User;
use App\Repositories\BusinessCardRepository;
use App\Repositories\UserRepository;
use Database\Seeders\DemoSeeder;
use RuntimeException;

class DatabaseTest extends TestCase
{
    public function testUserCreationAndAuthentication(): void
    {
        $userRepo = new UserRepository();
        $testEmail = 'unit_test_' . bin2hex(random_bytes(4)) . '@scanv.test';

        $user = $userRepo->create([
            'first_name' => 'Jean',
            'last_name' => 'Valjean',
            'login_email' => $testEmail,
            'password' => 'MonSecret123!',
            'status' => 'active',
        ]);

        $this->assertTrue($user->id !== null && $user->id > 0, 'User ID should be positive integer');
        $this->assertEquals($testEmail, $user->loginEmail);
        $this->assertTrue($user->verifyPassword('MonSecret123!'), 'Valid password must verify');
        $this->assertFalse($user->verifyPassword('WrongPassword'), 'Invalid password must not verify');

        // Check finding by email
        $found = $userRepo->findByEmail($testEmail);
        $this->assertTrue($found !== null, 'Should find user by email');
        $this->assertEquals($user->id, $found->id);
    }

    public function testBusinessCardCreationAndCascade(): void
    {
        $pdo = Database::getConnection();
        $userRepo = new UserRepository($pdo);
        $cardRepo = new BusinessCardRepository($pdo);

        $testEmail = 'cascade_test_' . bin2hex(random_bytes(4)) . '@scanv.test';
        $user = $userRepo->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'login_email' => $testEmail,
            'password' => 'Secret123!',
        ]);

        $publicId = Security::generatePublicId();
        $card = $cardRepo->create([
            'user_id' => $user->id,
            'public_id' => $publicId,
            'display_first_name' => 'Alice',
            'display_last_name' => 'Martin',
            'job_title' => 'Ingénieure Logiciel',
            'company_name' => 'ScanV Lab',
            'show_job_title' => 1,
            'show_company' => 1,
        ]);

        $this->assertTrue($card->id !== null && $card->id > 0);
        $this->assertEquals(32, strlen($card->publicId));

        // Add a link
        $cardRepo->addLink((int) $card->id, [
            'label' => 'Site',
            'url' => 'https://example.com',
        ]);

        $links = $cardRepo->getLinks((int) $card->id);
        $this->assertEquals(1, count($links));

        // Delete user -> ON DELETE CASCADE should delete card, visibility and links
        $delStmt = $pdo->prepare('DELETE FROM `users` WHERE `id` = :id');
        $delStmt->execute([':id' => $user->id]);

        $cardAfter = $cardRepo->findById((int) $card->id);
        $this->assertTrue($cardAfter === null, 'Card must be deleted via cascade when user is deleted');

        $linksAfter = $cardRepo->getLinks((int) $card->id, false);
        $this->assertEquals(0, count($linksAfter), 'Card links must be deleted via cascade');
    }

    public function testServerSideVisibilityFiltering(): void
    {
        $card = new BusinessCard(
            id: 999,
            userId: 1,
            publicId: str_repeat('a', 32),
            displayFirstName: 'Paul',
            displayLastName: 'Durand',
            jobTitle: 'Consultant',
            department: 'Direction Stratégie',
            companyName: 'Acme Corp',
            companyWebsite: 'https://acme.test',
            companyAddress: '1 rue Secret',
            professionalPhone: '+33600000000',
            professionalEmail: 'paul@acme.test'
        );

        // Visibility: only job_title and company visible; phone and email hidden
        $visibility = new CardVisibility(
            id: 1,
            cardId: 999,
            showPhoto: false,
            showJobTitle: true,
            showDepartment: false,
            showCompany: true,
            showCompanyAddress: false,
            showPhone: false,
            showEmail: false,
            showWebsite: false,
            showSocialLinks: false
        );

        $filtered = $visibility->filterCard($card);

        // Visible fields
        $this->assertEquals('Consultant', $filtered['job_title'] ?? null);
        $this->assertEquals('Acme Corp', $filtered['company_name'] ?? null);

        // Hidden fields MUST NOT be present in array (Rule 6)
        $this->assertFalse(array_key_exists('professional_phone', $filtered), 'Hidden phone must not exist in output array');
        $this->assertFalse(array_key_exists('professional_email', $filtered), 'Hidden email must not exist in output array');
        $this->assertFalse(array_key_exists('company_address', $filtered), 'Hidden address must not exist in output array');
        $this->assertFalse(array_key_exists('department', $filtered), 'Hidden department must not exist in output array');
    }

    public function testDemoSeederRejectsNonLocalEnvironment(): void
    {
        $originalEnv = (string) Env::get('APP_ENV', 'local');

        // Mock non-local environment
        Env::set('APP_ENV', 'production');

        $caught = false;
        try {
            $seeder = new DemoSeeder();
            $seeder->run();
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertStringContains('local', $e->getMessage());
        } finally {
            // Restore original
            Env::set('APP_ENV', $originalEnv);
        }

        $this->assertTrue($caught, 'DemoSeeder must throw RuntimeException when APP_ENV is not local');
    }
}
