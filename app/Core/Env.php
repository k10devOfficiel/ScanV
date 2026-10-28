<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight .env file loader and reader.
 */
class Env
{
    /**
     * @var array<string, mixed>
     */
    private static array $variables = [];

    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Split on the first '=' character
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $rawVal = trim($parts[1]);

            // Handle quoted strings
            if (
                (str_starts_with($rawVal, '"') && str_ends_with($rawVal, '"')) ||
                (str_starts_with($rawVal, "'") && str_ends_with($rawVal, "'"))
            ) {
                $value = substr($rawVal, 1, -1);
                if (str_starts_with($rawVal, '"')) {
                    $value = str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $value);
                }
            } else {
                // Remove inline comments if not quoted
                if (str_contains($rawVal, ' #')) {
                    $rawVal = trim(explode(' #', $rawVal, 2)[0]);
                }

                $lower = strtolower($rawVal);
                $value = match ($lower) {
                    'true', '(true)' => true,
                    'false', '(false)' => false,
                    'empty', '(empty)' => '',
                    'null', '(null)' => null,
                    default => $rawVal,
                };
            }

            self::$variables[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            if (is_scalar($value)) {
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Get an environment variable with optional default value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $envVal = getenv($key);
        if ($envVal !== false) {
            return $envVal;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Set or override an environment variable at runtime.
     */
    public static function set(string $key, mixed $value): void
    {
        self::$variables[$key] = $value;
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        if (is_scalar($value)) {
            putenv("{$key}={$value}");
        }
    }
}
