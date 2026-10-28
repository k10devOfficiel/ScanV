<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ContactExportService;
use App\Services\PublicCardService;

/**
 * Controller for public card viewing (F-12, F-13, F-14, F-17) and contact export (F-15).
 */
class PublicCardController extends Controller
{
    private PublicCardService $cardService;
    private ContactExportService $exportService;

    public function __construct(
        ?PublicCardService $cardService = null,
        ?ContactExportService $exportService = null
    ) {
        $this->cardService = $cardService ?? new PublicCardService();
        $this->exportService = $exportService ?? new ContactExportService();
    }

    /**
     * Export contact as vCard 3.0 (GET /c/{public_id}/vcard).
     */
    public function vcard(Request $request, string $public_id): Response
    {
        $result = $this->cardService->resolveCard($public_id);

        if ($result['status'] === PublicCardService::STATUS_NOT_FOUND) {
            return $this->renderUnavailable(404);
        }

        if ($result['status'] === PublicCardService::STATUS_INACTIVE) {
            return $this->renderUnavailable(410);
        }

        $cardView = $result['view'];
        $vcard = $this->exportService->generateVCard($cardView);
        $filename = $this->exportService->sanitizeFilename($cardView);

        $response = new Response($vcard, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($vcard),
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        return $response;
    }

    /**
     * Show public digital business card (GET /c/{public_id}).
     */
    public function show(Request $request, string $public_id): Response
    {
        $result = $this->cardService->resolveCard($public_id);

        if ($result['status'] === PublicCardService::STATUS_NOT_FOUND) {
            return $this->renderUnavailable(404);
        }

        if ($result['status'] === PublicCardService::STATUS_INACTIVE) {
            // Neutral page with HTTP 410 Gone (no data leakage)
            return $this->renderUnavailable(410);
        }

        $cardView = $result['view'];

        $response = $this->render('public-card/show', [
            'cardView' => $cardView,
            'title' => $cardView->displayName . ' - ScanV',
        ], 'public');

        // Rule: Avoid aggressive caching so updates appear immediately upon reload
        $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', '0');

        return $response;
    }

    /**
     * Render the neutral unavailable card view without any personal data.
     */
    private function renderUnavailable(int $statusCode): Response
    {
        $response = $this->render('public-card/unavailable', [
            'title' => 'Carte indisponible - ScanV',
        ], 'public', $statusCode);

        $response->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', '0');

        return $response;
    }
}
