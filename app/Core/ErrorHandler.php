<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use ErrorException;

/**
 * Global Error and Exception Handler.
 */
class ErrorHandler
{
    private static string $logPath = '';
    private static bool $debug = false;

    /**
     * Register global error, exception and shutdown handlers.
     */
    public static function register(string $storagePath, bool $debug = false): void
    {
        self::$logPath = rtrim($storagePath, '/\\') . DIRECTORY_SEPARATOR . 'logs';
        self::$debug = $debug;

        if (!is_dir(self::$logPath)) {
            @mkdir(self::$logPath, 0755, true);
        }

        error_reporting(E_ALL);

        if ($debug) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert standard PHP errors into ErrorException.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle uncaught exceptions.
     */
    public static function handleException(Throwable $e): void
    {
        self::logException($e);
        self::renderResponse($e);
    }

    /**
     * Handle fatal PHP errors at shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $e = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            self::logException($e);
            self::renderResponse($e);
        }
    }

    /**
     * Write error details to log file.
     */
    public static function logException(Throwable $e): void
    {
        $date = date('Y-m-d');
        $logFile = self::$logPath . DIRECTORY_SEPARATOR . "error-{$date}.log";
        $timestamp = date('Y-m-d H:i:s');

        $entry = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n%s\n",
            $timestamp,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
            str_repeat('-', 80)
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Render the 500 error response.
     */
    private static function renderResponse(Throwable $e): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $isJson = isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');

        if ($isJson) {
            header('Content-Type: application/json; charset=UTF-8');
            if (self::$debug) {
                echo json_encode([
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'error' => 'Une erreur interne est survenue. Veuillez réessayer plus tard.',
                ], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        if (self::$debug) {
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur Application</title>';
            echo '<style>body{font-family:sans-serif;padding:2rem;background:#fdf2f2;color:#333}h1{color:#991b1b}pre{background:#fff;padding:1rem;border:1px solid #f87171;overflow:auto}</style></head><body>';
            echo '<h1>' . htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8') . '</h1>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>Fichier:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' (ligne ' . (int)$e->getLine() . ')</p>';
            echo '<h3>Trace d\'exécution :</h3>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
            echo '</body></html>';
            exit;
        }

        // Production: clean semantic 500 view with no technical details
        $viewFile = dirname(__DIR__) . '/Views/errors/500.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur 500 - ScanV</title></head><body><h1>Erreur interne</h1><p>Une erreur est survenue. Veuillez réessayer plus tard.</p></body></html>';
        }
        exit;
    }
}
