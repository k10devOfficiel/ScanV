<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Helpers\View;

/**
 * Abstract Base Controller.
 */
abstract class Controller
{
    /**
     * Render an HTML view.
     *
     * @param string $viewPath
     * @param array<string, mixed> $data
     * @param string|null $layout
     */
    protected function render(string $viewPath, array $data = [], ?string $layout = 'base', int $statusCode = 200): Response
    {
        $html = View::render($viewPath, $data, $layout);
        return Response::html($html, $statusCode);
    }

    /**
     * Return a JSON response.
     */
    protected function json(mixed $data, int $statusCode = 200): Response
    {
        return Response::json($data, $statusCode);
    }
}
