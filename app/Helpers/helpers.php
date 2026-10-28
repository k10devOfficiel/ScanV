<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Response;
use App\Helpers\Security;
use App\Helpers\View;

if (!function_exists('e')) {
    /**
     * Escape HTML output using UTF-8 (Rule 3).
     */
    function e(mixed $value): string
    {
        return Security::e($value);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Retrieve or generate CSRF token.
     */
    function csrf_token(): string
    {
        return Security::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Render hidden input with CSRF token for forms.
     */
    function csrf_field(): string
    {
        return Security::csrfField();
    }
}

if (!function_exists('csrf_check')) {
    /**
     * Validate CSRF token against current session.
     */
    function csrf_check(?string $token = null): bool
    {
        return Security::csrfCheck($token);
    }
}

if (!function_exists('url')) {
    /**
     * Generate an absolute or root-relative URL.
     */
    function url(string $path = ''): string
    {
        $appUrl = (string) Env::get('APP_URL', '');
        $path = '/' . ltrim($path, '/');

        if ($appUrl !== '') {
            return rtrim($appUrl, '/') . $path;
        }

        return $path;
    }
}

if (!function_exists('view')) {
    /**
     * Render a view file with optional layout.
     *
     * @param string $viewPath e.g. 'landing/index'
     * @param array<string, mixed> $data
     * @param string|null $layout
     */
    function view(string $viewPath, array $data = [], ?string $layout = 'base'): string
    {
        return View::render($viewPath, $data, $layout);
    }
}

if (!function_exists('env')) {
    /**
     * Retrieve environment variable.
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect Response.
     */
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('auth_user')) {
    /**
     * Get current authenticated user or null.
     */
    function auth_user(): ?\App\Models\User
    {
        static $user = null;
        static $checked = false;

        if (!$checked) {
            $auth = new \App\Services\AuthService();
            $user = $auth->getCurrentUser();
            $checked = true;
        }

        return $user;
    }
}

if (!function_exists('current_user')) {
    /**
     * Alias for auth_user().
     */
    function current_user(): ?\App\Models\User
    {
        return auth_user();
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * Check if client is authenticated.
     */
    function is_logged_in(): bool
    {
        return auth_user() !== null;
    }
}

if (!function_exists('flash')) {
    /**
     * Set or retrieve a one-time flash message from session.
     */
    function flash(string $key, ?string $message = null): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if ($message !== null) {
            $_SESSION['flash'][$key] = $message;
            return null;
        }

        if (isset($_SESSION['flash'][$key])) {
            $val = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return (string) $val;
        }

        return null;
    }
}

if (!function_exists('mask_email')) {
    /**
     * Mask an email address for privacy (e.g. a***@domaine.com).
     */
    function mask_email(string $email): string
    {
        $parts = explode('@', trim($email));
        if (count($parts) !== 2 || $parts[0] === '') {
            return '***@***';
        }
        $name = $parts[0];
        $domain = $parts[1];
        $firstChar = mb_substr($name, 0, 1);
        return $firstChar . '***@' . $domain;
    }
}


