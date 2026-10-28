<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight PSR-4 autoloader for App namespace.
 */
class Autoloader
{
    private static bool $registered = false;

    public static function register(string $baseDir): void
    {
        if (self::$registered) {
            return;
        }

        // Register App\ and Database\ namespaces
        spl_autoload_register(function (string $class) use ($baseDir): void {
            $prefixes = [
                'App\\' => $baseDir . DIRECTORY_SEPARATOR . 'app',
                'Database\\' => $baseDir . DIRECTORY_SEPARATOR . 'database',
            ];

            foreach ($prefixes as $prefix => $dir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) === 0) {
                    $relativeClass = substr($class, $len);
                    $file = $dir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                        return;
                    }
                }
            }
        });

        self::$registered = true;
    }
}
