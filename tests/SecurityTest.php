<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Security;
use App\Middleware\CsrfMiddleware;

class SecurityTest extends TestCase
{
    public function testPublicIdGeneration(): void
    {
        $id1 = Security::generatePublicId();
        $id2 = Security::generatePublicId();

        $this->assertEquals(32, strlen($id1), 'Public ID should be exactly 32 hex chars');
        $this->assertEquals(32, strlen($id2), 'Public ID should be exactly 32 hex chars');
        $this->assertTrue(ctype_xdigit($id1), 'Public ID should only contain hexadecimal characters');
        $this->assertTrue($id1 !== $id2, 'Public IDs should be cryptographically random and unique');
    }

    public function testUrlSchemeValidation(): void
    {
        // Safe URLs
        $this->assertTrue(Security::isValidExternalUrl('https://example.com'));
        $this->assertTrue(Security::isValidExternalUrl('http://example.com/profile'));
        $this->assertTrue(Security::isValidExternalUrl('https://sub.domain.org/path?query=1'));

        // Forbidden schemes (Rule 8)
        $this->assertFalse(Security::isValidExternalUrl('javascript:alert(1)'));
        $this->assertFalse(Security::isValidExternalUrl('data:text/html;base64,PHNjcmlwdD4='));
        $this->assertFalse(Security::isValidExternalUrl('vbscript:msgbox(1)'));
        $this->assertFalse(Security::isValidExternalUrl('file:///etc/passwd'));
        $this->assertFalse(Security::isValidExternalUrl('not-a-url'));
    }

    public function testCsrfMiddlewareBlocksInvalidPost(): void
    {
        $middleware = new CsrfMiddleware();

        // POST without token should be blocked with 403
        $request = new Request('POST', '/submit', [], ['data' => 'test']);
        $response = $middleware->handle($request, function (): Response {
            return Response::html('Success');
        });

        $this->assertEquals(403, $response->getStatusCode(), 'POST without valid CSRF token should return 403');
    }

    public function testCsrfMiddlewareAllowsValidPost(): void
    {
        $middleware = new CsrfMiddleware();
        $token = Security::csrfToken();

        $request = new Request('POST', '/submit', [], ['_csrf_token' => $token]);
        $response = $middleware->handle($request, function (): Response {
            return Response::html('Success');
        });

        $this->assertEquals(200, $response->getStatusCode(), 'POST with valid CSRF token should return 200');
        $this->assertEquals('Success', $response->getContent());
    }
}
