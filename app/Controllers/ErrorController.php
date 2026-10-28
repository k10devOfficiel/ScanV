<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Controller for HTTP error pages.
 */
class ErrorController extends Controller
{
    /**
     * 404 Not Found response.
     */
    public function notFound(Request $request): Response
    {
        if ($request->isAjax()) {
            return $this->json(['error' => 'Ressource non trouvée.'], 404);
        }

        return $this->render('errors/404', [
            'title' => 'Page non trouvée - ScanV',
            'path' => $request->getPath(),
        ], 'base', 404);
    }

    /**
     * 500 Internal Server Error response.
     */
    public function serverError(Request $request): Response
    {
        if ($request->isAjax()) {
            return $this->json(['error' => 'Une erreur interne est survenue.'], 500);
        }

        return $this->render('errors/500', [
            'title' => 'Erreur interne - ScanV',
        ], 'base', 500);
    }
}
