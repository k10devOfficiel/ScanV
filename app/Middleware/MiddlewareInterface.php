<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param callable(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
