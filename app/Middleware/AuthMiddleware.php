<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Middleware ensuring user is authenticated for protected routes (/dashboard, /card*).
 */
class AuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->authService->getCurrentUser();

        if ($user === null) {
            if ($request->isAjax()) {
                return Response::json(['error' => 'Non authentifié. Veuillez vous connecter.'], 401);
            }

            // Redirect unauthenticated user to /login
            return Response::redirect(url('/login'), 302);
        }

        // Pass authenticated user object into request attributes
        $request->setAttribute('authenticated_user', $user);

        return $next($request);
    }
}
