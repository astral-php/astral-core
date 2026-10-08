<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Container;
use Core\Exception\NotFoundException;
use Core\Request;
use Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du routeur.
 */
final class RouterTest extends TestCase
{
    private function makeRouter(string $method, string $uri): Router
    {
        // Simule la requête via les superglobales
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;

        $request   = new Request();
        $container = new Container();

        return new Router(request: $request, container: $container);
    }

    public function testDispatchThrowsNotFoundForUnknownRoute(): void
    {
        $router = $this->makeRouter('GET', '/unknown-route-xyz');

        $this->expectException(NotFoundException::class);
        $router->dispatch();
    }

    public function testAddAndResolveStaticRoute(): void
    {
        // On teste que le dispatch ne lève PAS NotFoundException
        // pour une route enregistrée (on utilise un controller factice)
        $router = $this->makeRouter('GET', '/test-route');

        // Contrôleur anonyme enregistré dans le container
        $router->get('/test-route', FakeController::class, 'handle');

        $container = new Container();
        $container->bind(FakeController::class, fn() => new FakeController());

        // On vérifie juste que la résolution fonctionne sans exception
        $this->expectNotToPerformAssertions();

        try {
            $router->dispatch();
        } catch (NotFoundException $e) {
            $this->fail('Route statique non trouvée : ' . $e->getMessage());
        }
    }
}

// Contrôleur factice pour les tests
final class FakeController
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}
