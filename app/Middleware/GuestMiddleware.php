<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Middleware ensuring only unauthenticated guests can access auth pages (login, register).
 */
class GuestMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->authService->getCurrentUser();

        if ($user !== null) {
            // Already authenticated -> redirect to dashboard
            return Response::redirect(url('/dashboard'), 302);
        }

        return $next($request);
    }
}
