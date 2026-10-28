<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CardRepository;
use App\Services\AccountService;
use App\Services\AuthService;
use App\Services\CardThemeService;

/**
 * Controller for protected dashboard and user management actions:
 * - Overview & previews (F-08, F-10, F-11).
 * - Activation / deactivation toggle (F-17).
 * - Account deletion with password confirmation (F-18).
 */
class DashboardController extends Controller
{
    private AuthService $authService;
    private CardRepository $cardRepo;
    private AccountService $accountService;

    public function __construct(
        ?AuthService $authService = null,
        ?CardRepository $cardRepo = null,
        ?AccountService $accountService = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->cardRepo = $cardRepo ?? new CardRepository();
        $this->accountService = $accountService ?? new AccountService(cardRepo: $this->cardRepo);
    }

    /**
     * Show dashboard (GET /dashboard).
     */
    public function index(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        $card = $this->cardRepo->findByUserId((int) $user->id);
        $visibility = $card ? $this->cardRepo->getVisibility((int) $card->id) : null;
        $links = $card ? $this->cardRepo->getLinks((int) $card->id, true) : [];
        $theme = CardThemeService::getTheme($card?->themeKey);

        return $this->render('dashboard/index', [
            'title' => 'Tableau de bord - ScanV',
            'user' => $user,
            'card' => $card,
            'visibility' => $visibility,
            'links' => $links,
            'theme' => $theme,
            'themes' => CardThemeService::getAllThemes(),
            'flashSuccess' => flash('success'),
            'flashError' => flash('error'),
        ]);
    }

    /**
     * Toggle business card activation state (POST /card/toggle-status) (F-17).
     */
    public function toggleActive(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        $result = $this->accountService->toggleCardActive((int) $user->id);
        if ($result['success']) {
            flash('success', $result['message']);
        } else {
            flash('error', $result['message']);
        }

        return Response::redirect(url('/dashboard'), 302);
    }

    /**
     * Delete user account with password confirmation (POST /account/delete) (F-18).
     */
    public function deleteAccount(Request $request): Response
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            return Response::redirect(url('/login'), 302);
        }

        $password = (string) $request->post('password', '');
        if ($password === '') {
            flash('error', 'Veuillez saisir votre mot de passe pour confirmer la suppression définitive de votre compte.');
            return Response::redirect(url('/dashboard'), 302);
        }

        $result = $this->accountService->deleteAccount((int) $user->id, $password);

        if (!$result['success']) {
            flash('error', (string) $result['error']);
            return Response::redirect(url('/dashboard'), 302);
        }

        // Logout session completely
        $this->authService->logout();
        flash('success', 'Votre compte et l\'intégralité des données associées ont été définitivement supprimés.');

        return Response::redirect(url('/login'), 302);
    }
}
