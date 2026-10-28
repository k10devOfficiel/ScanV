<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\DashboardController;
use App\Core\Database;
use App\Core\Request;
use App\Middleware\AuthMiddleware;
use App\Repositories\BusinessCardRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

class AuthTest extends TestCase
{
    private AuthService $authService;
    private UserRepository $userRepo;
    private BusinessCardRepository $cardRepo;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->userRepo = new UserRepository($pdo);
        $this->cardRepo = new BusinessCardRepository($pdo);
        $this->authService = new AuthService($pdo, $this->userRepo, $this->cardRepo);
    }

    public function testDuplicateEmailRegistrationFails(): void
    {
        $email = 'duplicate_' . bin2hex(random_bytes(4)) . '@scanv.test';

        // 1. First registration
        $result1 = $this->authService->register([
            'first_name' => 'Claire',
            'last_name' => 'Dubois',
            'login_email' => $email,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'terms' => '1',
        ]);
        $this->assertTrue($result1['success'], 'First registration should succeed');

        // 2. Second registration with the same email
        $result2 = $this->authService->register([
            'first_name' => 'Autre',
            'last_name' => 'Personne',
            'login_email' => $email,
            'password' => 'AnotherPass123!',
            'password_confirmation' => 'AnotherPass123!',
            'terms' => '1',
        ]);

        $this->assertFalse($result2['success'], 'Duplicate email registration must fail');
        $this->assertTrue(isset($result2['errors']['login_email']), 'Error message must exist for login_email');
        $this->assertStringContains('déjà associée', $result2['errors']['login_email'], 'Must inform that email is already registered');
    }

    public function testPasswordTooShortRegistrationFails(): void
    {
        $email = 'shortpass_' . bin2hex(random_bytes(4)) . '@scanv.test';

        $result = $this->authService->register([
            'first_name' => 'Marc',
            'last_name' => 'Bernard',
            'login_email' => $email,
            'password' => 'Short1!', // 7 chars, less than 10
            'password_confirmation' => 'Short1!',
            'terms' => '1',
        ]);

        $this->assertFalse($result['success'], 'Registration with short password must fail');
        $this->assertTrue(isset($result['errors']['password']), 'Error must exist for password');
        $this->assertStringContains('10 caractères', $result['errors']['password']);
    }

    public function testSuccessfulRegistrationCreatesUserAndBusinessCard(): void
    {
        $email = 'card_init_' . bin2hex(random_bytes(4)) . '@scanv.test';

        $result = $this->authService->register([
            'first_name' => 'Sophie',
            'last_name' => 'Lemoine',
            'login_email' => $email,
            'password' => 'StrongPassword2026!',
            'password_confirmation' => 'StrongPassword2026!',
            'terms' => '1',
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['user'] !== null);
        $this->assertTrue($result['card'] !== null);

        // Verify card attributes
        $card = $result['card'];
        $this->assertEquals(32, strlen($card->publicId), 'Public ID must be 32 hex chars');
        $this->assertEquals('Sophie', $card->displayFirstName, 'display_first_name must be prefilled');
        $this->assertEquals('Lemoine', $card->displayLastName, 'display_last_name must be prefilled');
        $this->assertTrue($card->professionalEmail === null, 'professional_email must NEVER be copied automatically from login_email');

        // Verify card visibility defaults
        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        $this->assertTrue($visibility !== null, 'Card visibility must be created');
        $this->assertTrue($visibility->showJobTitle, 'show_job_title must default to 1');
        $this->assertTrue($visibility->showCompany, 'show_company must default to 1');
        $this->assertFalse($visibility->showPhone, 'show_phone must default to 0');
    }

    public function testAccessDashboardWithoutSessionRedirectsToLogin(): void
    {
        // Ensure no active session
        $this->authService->logout();

        $request = new Request('GET', '/dashboard');
        $middleware = new AuthMiddleware($this->authService);

        $response = $middleware->handle($request, function (Request $req) {
            $controller = new DashboardController();
            return $controller->index($req);
        });

        $this->assertEquals(302, $response->getStatusCode(), 'Unauthorized access must return 302 redirect');
        $headers = $response->getHeaders();
        $this->assertStringContains('/login', $headers['Location'] ?? '', 'Location header must point to /login');
    }

    public function testSuccessfulLogin(): void
    {
        $email = 'login_ok_' . bin2hex(random_bytes(4)) . '@scanv.test';
        $password = 'CorrectPassword123!';

        // Create user
        $this->authService->register([
            'first_name' => 'Luc',
            'last_name' => 'Besson',
            'login_email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'terms' => '1',
        ]);

        // Log out first
        $this->authService->logout();

        // Perform login
        $result = $this->authService->login($email, $password, '127.0.0.1');

        $this->assertTrue($result['success'], 'Login with correct credentials must succeed');
        $this->assertTrue($result['user'] !== null);
        $this->assertEquals($email, $result['user']->loginEmail);

        // Verify session user is authenticated
        $currentUser = $this->authService->getCurrentUser();
        $this->assertTrue($currentUser !== null, 'Current session user should be authenticated');
        $this->assertEquals($email, $currentUser->loginEmail);
    }

    public function testFailedLoginWithGenericMessage(): void
    {
        // Ensure clean state
        $this->authService->logout();

        $result = $this->authService->login('non_existent@scanv.test', 'RandomPassword123!', '127.0.0.1');

        $this->assertFalse($result['success'], 'Login with unknown email must fail');
        $this->assertTrue($result['error'] !== null);
        // Generic message without revealing email non-existence (F-03)
        $this->assertStringContains('incorrect', $result['error']);
    }

    public function testRateLimitingAfterConsecutiveFailures(): void
    {
        $ip = '192.168.1.99';
        $email = 'bruteforce_' . bin2hex(random_bytes(4)) . '@scanv.test';

        // Clear any previous attempts
        $this->authService->clearLoginAttempts($ip, $email);

        // Perform 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->authService->login($email, 'WrongPass123!', $ip);
        }

        // 6th attempt should be blocked by rate limiter
        $result = $this->authService->login($email, 'WrongPass123!', $ip);

        $this->assertFalse($result['success']);
        $this->assertStringContains('Trop de tentatives', $result['error'] ?? '', 'Must be blocked by rate limit');
    }
}
