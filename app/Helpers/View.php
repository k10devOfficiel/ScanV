<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * View renderer with layout support.
 */
class View
{
    private static string $viewsDir = '';

    public static function setViewsDir(string $path): void
    {
        self::$viewsDir = rtrim($path, '/\\');
    }

    public static function getViewsDir(): string
    {
        if (self::$viewsDir === '') {
            self::$viewsDir = dirname(__DIR__) . '/Views';
        }
        return self::$viewsDir;
    }

    /**
     * Render a view file with layout.
     *
     * @param string $view Relative view path without .php (e.g. 'landing/index')
     * @param array<string, mixed> $data Data to extract into view scope
     * @param string|null $layout Layout name in Views/layouts/ or null for no layout
     */
    public static function render(string $view, array $data = [], ?string $layout = 'base'): string
    {
        $base = self::getViewsDir();
        $viewFile = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!file_exists($viewFile)) {
            throw new InvalidArgumentException("View file not found: {$viewFile}");
        }

        // Render view content
        $content = self::renderFile($viewFile, $data);

        // Render inside layout if requested
        if ($layout !== null) {
            $layoutFile = $base . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . $layout . '.php';
            if (!file_exists($layoutFile)) {
                throw new InvalidArgumentException("Layout file not found: {$layoutFile}");
            }

            $layoutData = array_merge($data, ['content' => $content]);
            return self::renderFile($layoutFile, $layoutData);
        }

        return $content;
    }

    private static function renderFile(string $filePath, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $filePath;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
