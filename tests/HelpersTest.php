<?php

declare(strict_types=1);

namespace Tests;

use App\Helpers\Security;

class HelpersTest extends TestCase
{
    public function testHtmlEscapeHelper(): void
    {
        $xss = '<script>alert("XSS")</script>';
        $escaped = e($xss);

        $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $escaped);
        $this->assertEquals('', e(null));
        $this->assertEquals('123', e(123));
    }

    public function testCsrfTokenGenerationAndValidation(): void
    {
        $token = csrf_token();
        $this->assertTrue(is_string($token) && strlen($token) === 64, 'Token should be 64-char hex string');

        $field = csrf_field();
        $this->assertStringContains('type="hidden"', $field);
        $this->assertStringContains('name="_csrf_token"', $field);
        $this->assertStringContains($token, $field);

        $this->assertTrue(csrf_check($token), 'Valid token should pass check');
        $this->assertFalse(csrf_check('invalid-token'), 'Invalid token should fail check');
        $this->assertFalse(csrf_check(''), 'Empty token should fail check');
    }

    public function testUrlHelper(): void
    {
        $url = url('/assets/css/tokens.css');
        $this->assertStringContains('/assets/css/tokens.css', $url);

        $root = url('/');
        $this->assertStringContains('/', $root);
    }
}
