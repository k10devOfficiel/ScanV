<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Security helper class for CSRF and sanitization.
 */
class Security
{
    private const CSRF_KEY = '_csrf_token';

    /**
     * Get or generate the current session CSRF token.
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    /**
     * Render an HTML hidden input for CSRF protection.
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Verify the CSRF token.
     */
    public static function csrfCheck(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $sessionToken = $_SESSION[self::CSRF_KEY] ?? null;
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        if ($token === null) {
            $token = $_POST['_csrf_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? null;
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Escape HTML content (helper e()).
     */
    public static function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate a 32-hex random public_id for cards (Rule 5).
     */
    public static function generatePublicId(): string
    {
        return bin2hex(random_bytes(16)); // 16 bytes = 32 hex characters
    }

    /**
     * Validate URL scheme (Rule 8: only http and https allowed).
     */
    public static function isValidExternalUrl(string $url): bool
    {
        $filtered = filter_var($url, FILTER_VALIDATE_URL);
        if ($filtered === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        return in_array(strtolower((string) $scheme), ['http', 'https'], true);
    }
}
