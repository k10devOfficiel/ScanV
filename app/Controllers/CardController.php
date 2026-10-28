<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CardRepository;
use App\Services\AuthService;
use App\Services\CardImageService;
use App\Services\CardPdfService;
use App\Services\CardRenderService;
use App\Services\CardService;
use App\Services\CardThemeService;
use App\Services\QrCodeService;

/**
 * Controller for editing card, managing links, visibility, and serving uploaded media.
 */
class CardController extends Controller
{
    private AuthService $authService;
    private CardService $cardService;
    private CardRepository $cardRepo;
    private QrCodeService $qrCodeService;
    private CardRenderService $cardRenderService;
    private CardImageService $cardImageService;
    private CardPdfService $cardPdfService;

    public function __construct(
        ?AuthService $authService = null,
        ?CardService $cardService = null,
        ?CardRepository $cardRepo = null,
        ?QrCodeService $qrCodeService = null,
        ?CardRenderService $cardRenderService = null,
        ?CardImageService $cardImageService = null,
        ?CardPdfService $cardPdfService = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->cardService = $cardService ?? new CardService();
        $this->cardRepo = $cardRepo ?? new CardRepository();
        $this->qrCodeService = $qrCodeService ?? new QrCodeService();
        $this->cardRenderService = $cardRenderService ?? new CardRenderService($this->cardRepo, $this->qrCodeService);
        $this->cardImageService = $cardImageService ?? new CardImageService($this->cardRepo, $this->cardRenderService, $this->qrCodeService);
        $this->cardPdfService = $cardPdfService ?? new CardPdfService($this->cardRepo, $this->qrCodeService);
    }

    /**
     * Show card editor form (GET /card/edit).
     */
    public function edit(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        $links = $this->cardRepo->getLinks((int) $card->id, false);

        $saved = $request->query('saved') === '1';

        return $this->render('card-editor/edit', [
            'title' => 'Édition de votre carte - ScanV',
            'user' => $user,
            'card' => $card,
            'visibility' => $visibility,
            'links' => $links,
            'themes' => CardThemeService::getAllThemes(),
            'errors' => [],
            'success' => $saved ? 'Votre carte de visite a été enregistrée avec succès.' : null,
            'old' => [],
        ]);
    }

    /**
     * Handle card update submission (POST /card/edit).
     */
    public function update(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        $result = $this->cardService->updateCard((int) $user->id, $request->all(), $_FILES);

        if (!$result['success']) {
            $card = $this->cardService->getCardForUser((int) $user->id);
            $visibility = $this->cardRepo->getVisibility((int) $card->id);
            $links = $this->cardRepo->getLinks((int) $card->id, false);

            return $this->render('card-editor/edit', [
                'title' => 'Édition de votre carte - ScanV',
                'user' => $user,
                'card' => $card,
                'visibility' => $visibility,
                'links' => $links,
                'themes' => CardThemeService::getAllThemes(),
                'errors' => $result['errors'],
                'success' => null,
                'old' => $request->all(),
            ], 'base', 422);
        }

        return Response::redirect(url('/card/edit?saved=1'), 302);
    }

    /**
     * Serve private uploads safely through controlled route (GET /uploads/{filename}).
     * Enforces Rule 6 (server-side visibility) and prevents unauthorized direct downloads.
     */
    public function serveUpload(Request $request, string $filename): Response
    {
        $currentUser = $this->authService->getCurrentUser();
        $currentUserId = $currentUser !== null ? (int) $currentUser->id : null;

        if (!$this->cardService->isUploadAuthorized($filename, $currentUserId)) {
            return Response::html('Fichier introuvable.', 404);
        }

        $filePath = $this->cardService->getUploadPath($filename);

        if ($filePath === null || !file_exists($filePath)) {
            return Response::html('Fichier introuvable.', 404);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $filePath) : 'application/octet-stream';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return Response::html('Erreur de lecture du fichier.', 500);
        }

        $response = new Response($content, 200, [
            'Content-Type' => (string) $mime,
            'Content-Length' => (string) strlen($content),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);

        return $response;
    }

    /**
     * Private route serving PNG QR code for authenticated user's own card (GET /card/qr.png).
     * Enforces Rule 9 (card ownership check) and Rule 7 (only public URL encoded).
     */
    public function qrCodePng(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $size = (int) ($request->query('size') ?: QrCodeService::DEFAULT_SIZE);
        $margin = (int) ($request->query('margin') ?: QrCodeService::DEFAULT_MARGIN);
        $ec = (string) ($request->query('ec') ?: QrCodeService::DEFAULT_ERROR_CORRECTION);

        try {
            $pngData = $this->qrCodeService->generatePng((string) $card->publicId, $size, $margin, $ec);
        } catch (\InvalidArgumentException $e) {
            return Response::html('Paramètres de QR code invalides.', 400);
        }

        return new Response($pngData, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($pngData),
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Preview front face (Recto) of authenticated user's card (GET /card/preview/front).
     * Enforces Rule 9 (card ownership check).
     */
    public function previewFront(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        $html = $this->cardRenderService->renderFront($card, $visibility, true, false);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }

    /**
     * Preview back face (Verso) of authenticated user's card (GET /card/preview/back).
     * Enforces Rule 9 (card ownership check).
     */
    public function previewBack(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        $html = $this->cardRenderService->renderBack($card, $visibility, true, false);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Cache-Control' => 'private, no-cache, must-revalidate',
        ]);
    }

    /**
     * Download front face (Recto) PNG 1004x650 @ 300 DPI (GET /card/download/front.png).
     * Enforces Rule 9 (user ownership) and serves as attachment.
     */
    public function downloadFrontPng(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $pngPath = $this->cardImageService->getCardPng($card, 'front');
        if (!file_exists($pngPath)) {
            return Response::html('Impossible de générer le fichier PNG.', 500);
        }

        $downloadName = $this->cardImageService->getDownloadFilename($card, 'front');
        $content = file_get_contents($pngPath);
        if ($content === false) {
            return Response::html('Erreur de lecture du fichier PNG.', 500);
        }

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Download back face (Verso) PNG 1004x650 @ 300 DPI (GET /card/download/back.png).
     * Enforces Rule 9 (user ownership) and serves as attachment.
     */
    public function downloadBackPng(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $pngPath = $this->cardImageService->getCardPng($card, 'back');
        if (!file_exists($pngPath)) {
            return Response::html('Impossible de générer le fichier PNG.', 500);
        }

        $downloadName = $this->cardImageService->getDownloadFilename($card, 'back');
        $content = file_get_contents($pngPath);
        if ($content === false) {
            return Response::html('Erreur de lecture du fichier PNG.', 500);
        }

        return new Response($content, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Download print-ready PDF (2 pages 85x55mm) (GET /card/download/card.pdf).
     * Enforces Rule 9 (user ownership) and serves as attachment.
     */
    public function downloadPdf(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        try {
            $card = $this->cardService->getCardForUser((int) $user->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 403) {
                return Response::html('Accès interdit.', 403);
            }
            return Response::html('Carte introuvable.', 404);
        }

        $pdfPath = $this->cardPdfService->getCardPdf($card);
        if (!file_exists($pdfPath)) {
            return Response::html('Impossible de générer le fichier PDF.', 500);
        }

        $downloadName = $this->cardPdfService->getDownloadFilename($card);
        $content = file_get_contents($pdfPath);
        if ($content === false) {
            return Response::html('Erreur de lecture du fichier PDF.', 500);
        }

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
