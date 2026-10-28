<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Helpers\Security;

/**
 * Middleware that verifies CSRF tokens on state-changing requests.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $method = $request->getMethod();

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $token = $request->post('_csrf_token')
                ?? $request->header('X-CSRF-TOKEN')
                ?? null;

            if (!is_string($token) || !Security::csrfCheck($token)) {
                if ($request->isAjax()) {
                    return Response::json(['error' => 'Jeton CSRF invalide ou expiré.'], 403);
                }

                return Response::html(
                    '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>403 - Non autorisé</title></head><body><h1>403 - Jeton CSRF invalide ou expiré</h1><p>Veuillez rafraîchir la page et réessayer.</p><p><a href="' . htmlspecialchars(url('/'), ENT_QUOTES, 'UTF-8') . '">Retour à l\'accueil</a></p></body></html>',
                    403
                );
            }
        }

        return $next($request);
    }
}
