<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

/**
 * Controller for public home and landing pages.
 */
class HomeController extends Controller
{
    /**
     * Display the home landing page.
     */
    public function index(Request $request): Response
    {
        return $this->render('landing/index', [
            'title' => 'ScanV – Ta carte de visite, toujours à jour',
        ]);
    }

    /**
     * Display Terms of Service.
     */
    public function terms(Request $request): Response
    {
        return $this->render('legal/terms', [
            'title' => 'Conditions d\'utilisation – ScanV',
        ]);
    }

    /**
     * Display Privacy Policy.
     */
    public function privacy(Request $request): Response
    {
        return $this->render('legal/privacy', [
            'title' => 'Politique de confidentialité – ScanV',
        ]);
    }
}
