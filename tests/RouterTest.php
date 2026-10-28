<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\HomeController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\MiddlewareInterface;

class RouterTest extends TestCase
{
    public function testHomeRouteMatches(): void
    {
        $router = new Router();
        $router->get('/', [HomeController::class, 'index']);

        $request = new Request('GET', '/');
        $response = $router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode(), 'Home route should return 200 OK');
        $this->assertStringContains('ScanV', $response->getContent(), 'Home response should contain ScanV');
    }

    public function testRouteParameterExtraction(): void
    {
        $router = new Router();
        $router->get('/cards/{id}', function (Request $req, string $id): Response {
            return Response::json(['card_id' => $id]);
        });

        $request = new Request('GET', '/cards/abc-123');
        $response = $router->dispatch($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContains('abc-123', $response->getContent());
    }

    public function testNotFoundRouteReturns404(): void
    {
        $router = new Router();
        $router->get('/', function (): Response {
            return Response::html('Home');
        });

        $request = new Request('GET', '/non-existent-page');
        $response = $router->dispatch($request);

        $this->assertEquals(404, $response->getStatusCode(), 'Unknown route should return 404');
        $this->assertStringContains('404', $response->getContent(), 'Should contain 404 text');
    }

    public function testMiddlewareExecution(): void
    {
        $router = new Router();

        $customMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                $response->setHeader('X-Custom-Header', 'Passed');
                return $response;
            }
        };

        $router->get('/test-middleware', function (): Response {
            return Response::html('OK');
        }, [$customMiddleware]);

        $request = new Request('GET', '/test-middleware');
        $response = $router->dispatch($request);

        $headers = $response->getHeaders();
        $this->assertEquals('Passed', $headers['X-Custom-Header'] ?? null, 'Middleware should have set header');
    }
}
