<?php

declare(strict_types=1);

// Built-in PHP CLI web server router support
if (php_sapi_name() === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ($reqPath !== null && $reqPath !== '/' && file_exists(__DIR__ . $reqPath) && !is_dir(__DIR__ . $reqPath)) {
        return false;
    }
}

/**
 * ScanV - Front Controller
 */

define('SCANV_START', microtime(true));
define('ROOT_PATH', dirname(__DIR__));

// 1. Autoloader
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}
require_once ROOT_PATH . '/app/Core/Autoloader.php';
App\Core\Autoloader::register(ROOT_PATH);

// 2. Load Helpers
require_once ROOT_PATH . '/app/Helpers/helpers.php';

// 3. Load Environment (.env)
App\Core\Env::load(ROOT_PATH . '/.env');

// 4. Configuration
$config = require ROOT_PATH . '/config/app.php';

// 5. Register Error & Exception Handler
$debug = (bool) ($config['debug'] ?? false);
App\Core\ErrorHandler::register(ROOT_PATH . '/storage', $debug);

// 6. Security Headers (PHP level, works with php -S and Apache/Nginx)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self';");

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (($config['env'] ?? '') === 'production');

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// 7. Secure Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = (int) ($config['session']['lifetime'] ?? 7200);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (($config['env'] ?? '') === 'production');
    $secure = $isHttps || (bool) ($config['session']['secure'] ?? false);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (!empty($config['session']['name'])) {
        session_name($config['session']['name']);
    }

    session_start();
}

// 8. Routing
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

$request = Request::createFromGlobals();
$router = new Router();

// Public routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/conditions-utilisation', [HomeController::class, 'terms']);
$router->get('/politique-confidentialite', [HomeController::class, 'privacy']);

// Guest routes (login / register / forgot-password / OTP flow)
$router->get('/register', [AuthController::class, 'showRegister'], [GuestMiddleware::class]);
$router->post('/register', [AuthController::class, 'register'], [CsrfMiddleware::class, GuestMiddleware::class]);

$router->get('/login', [AuthController::class, 'showLogin'], [GuestMiddleware::class]);
$router->post('/login', [AuthController::class, 'login'], [CsrfMiddleware::class, GuestMiddleware::class]);

// 3-step OTP password reset routes
$router->get('/forgot', [AuthController::class, 'showForgot'], [GuestMiddleware::class]);
$router->post('/forgot', [AuthController::class, 'forgot'], [CsrfMiddleware::class, GuestMiddleware::class]);

$router->get('/forgot/verify', [AuthController::class, 'showVerifyOtp'], [GuestMiddleware::class]);
$router->post('/forgot/verify', [AuthController::class, 'verifyOtp'], [CsrfMiddleware::class, GuestMiddleware::class]);
$router->post('/forgot/resend', [AuthController::class, 'resendOtp'], [CsrfMiddleware::class, GuestMiddleware::class]);

$router->get('/forgot/new-password', [AuthController::class, 'showNewPassword'], [GuestMiddleware::class]);
$router->post('/forgot/new-password', [AuthController::class, 'newPassword'], [CsrfMiddleware::class, GuestMiddleware::class]);

// Backward compatibility redirects / aliases
$router->get('/forgot-password', [AuthController::class, 'showForgot'], [GuestMiddleware::class]);
$router->post('/forgot-password', [AuthController::class, 'forgot'], [CsrfMiddleware::class, GuestMiddleware::class]);
$router->get('/reset-password', [AuthController::class, 'showResetPassword'], [GuestMiddleware::class]);
$router->post('/reset-password', [AuthController::class, 'resetPassword'], [CsrfMiddleware::class, GuestMiddleware::class]);

// Authenticated routes
$router->post('/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->post('/card/toggle-status', [DashboardController::class, 'toggleActive'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->post('/account/delete', [DashboardController::class, 'deleteAccount'], [CsrfMiddleware::class, AuthMiddleware::class]);

// Card editing & export routes (F-05, F-06, F-07, F-08, F-09, F-10, F-16)
use App\Controllers\CardController;
$router->get('/card/edit', [CardController::class, 'edit'], [AuthMiddleware::class]);
$router->post('/card/edit', [CardController::class, 'update'], [CsrfMiddleware::class, AuthMiddleware::class]);
$router->get('/card/qr.png', [CardController::class, 'qrCodePng'], [AuthMiddleware::class]);
$router->get('/card/preview/front', [CardController::class, 'previewFront'], [AuthMiddleware::class]);
$router->get('/card/preview/back', [CardController::class, 'previewBack'], [AuthMiddleware::class]);
$router->get('/card/download/front.png', [CardController::class, 'downloadFrontPng'], [AuthMiddleware::class]);
$router->get('/card/download/back.png', [CardController::class, 'downloadBackPng'], [AuthMiddleware::class]);
$router->get('/card/download/card.pdf', [CardController::class, 'downloadPdf'], [AuthMiddleware::class]);

// Controlled upload serving routes (F-16)
$router->get('/uploads/{filename}', [CardController::class, 'serveUpload']);
$router->get('/storage/uploads/{filename}', [CardController::class, 'serveUpload']);

// Public digital business card routes (F-12, F-13, F-14, F-15, F-17)
use App\Controllers\PublicCardController;
$router->get('/c/{public_id}/vcard', [PublicCardController::class, 'vcard']);
$router->get('/c/{public_id}', [PublicCardController::class, 'show']);

// Dispatch request and send response
$response = $router->dispatch($request);
$response->send();
