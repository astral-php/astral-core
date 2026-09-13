<?php

declare(strict_types=1);

namespace Core;

use Core\Exception\NotFoundException;
use Core\Http\Response;
use Core\Middleware\MiddlewareInterface;

/**
 * Routeur Front Controller.
 *
 * Fonctionnalités :
 *   - Routes GET / POST / PUT / PATCH / DELETE
 *   - Paramètres dynamiques (:id, :slug…)
 *   - Middleware par route ou par groupe
 *   - Groupes de routes avec préfixe URI commun
 *   - Pipeline de middleware avant le contrôleur
 */
final class Router
{
    /**
     * @var array<string, array<string, array{
     *     controller: string,
     *     action: string,
     *     middleware: list<string>
     * }>>
     */
    private array $routes = [];

    private string $currentPrefix    = '';

    /** @var list<string> */
    private array $groupMiddleware   = [];

    private string $lastMethod       = '';
    private string $lastPath         = '';

    public function __construct(
        private Request   $request,
        private Container $container,
    ) {}

    // -------------------------------------------------------------------------
    // API d'enregistrement
    // -------------------------------------------------------------------------

    public function get(string $path, string $controller, string $action): self
    {
        return $this->addRoute('GET', $path, $controller, $action);
    }

    public function post(string $path, string $controller, string $action): self
    {
        return $this->addRoute('POST', $path, $controller, $action);
    }

    public function put(string $path, string $controller, string $action): self
    {
        return $this->addRoute('PUT', $path, $controller, $action);
    }

    public function patch(string $path, string $controller, string $action): self
    {
        return $this->addRoute('PATCH', $path, $controller, $action);
    }

    public function delete(string $path, string $controller, string $action): self
    {
        return $this->addRoute('DELETE', $path, $controller, $action);
    }

    /**
     * Ajoute un ou plusieurs middleware à la dernière route enregistrée.
     *
     * @param string|list<string> $middleware
     */
    public function middleware(string|array $middleware): self
    {
        if ($this->lastMethod !== '' && $this->lastPath !== '') {
            $existing = $this->routes[$this->lastMethod][$this->lastPath]['middleware'];
            $this->routes[$this->lastMethod][$this->lastPath]['middleware']
                = array_merge($existing, (array) $middleware);
        }

        return $this;
    }

    /**
     * Groupe de routes partageant un préfixe URI et/ou des middleware communs.
     *
     * @param list<string> $middleware
     *
     * Usage :
     *   $router->group('/admin', function (Router $r) {
     *       $r->get('/dashboard', AdminController::class, 'index');
     *   }, [AuthMiddleware::class]);
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $previousPrefix     = $this->currentPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->currentPrefix   = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->currentPrefix   = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function dispatch(): void
    {
        $method = $this->request->method;
        $uri    = $this->request->uri;

        [$route, $params] = $this->resolve($method, $uri);

        if ($route === null) {
            throw new NotFoundException("Aucune route trouvée pour [{$method}] {$uri}");
        }

        $controllerClass = $route['controller'];
        $action          = $route['action'];
        $middlewares     = $route['middleware'];

        if (!class_exists($controllerClass)) {
            throw new NotFoundException("Contrôleur introuvable : {$controllerClass}");
        }

        $controller = $this->container->make($controllerClass);

        if (!method_exists($controller, $action)) {
            throw new NotFoundException("Action introuvable : {$controllerClass}::{$action}");
        }

        $response = $this->runWithMiddleware($middlewares, function () use ($controller, $action, $params): mixed {
            return $controller->$action(...$params);
        });

        if ($response instanceof Response) {
            $response->send();
        }
    }

    // -------------------------------------------------------------------------
    // Pipeline middleware
    // -------------------------------------------------------------------------

    /** @param list<string> $middlewareClasses */
    private function runWithMiddleware(array $middlewareClasses, callable $action): mixed
    {
        $request  = $this->request;
        $pipeline = $action;

        foreach (array_reverse($middlewareClasses) as $class) {
            /** @var MiddlewareInterface $mw */
            $mw   = $this->container->make($class);
            $next = $pipeline;

            $pipeline = function () use ($mw, $request, $next): mixed {
                return $mw->handle($request, $next);
            };
        }

        return $pipeline();
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function addRoute(string $method, string $path, string $controller, string $action): self
    {
        $full = $this->currentPrefix . ($path !== '/' ? rtrim($path, '/') : $path);
        $full = $full ?: '/';

        $this->routes[$method][$full] = [
            'controller' => $controller,
            'action'     => $action,
            'middleware' => $this->groupMiddleware,
        ];

        $this->lastMethod = $method;
        $this->lastPath   = $full;

        return $this;
    }

    /**
     * @return array{
     *     0: array{controller:string, action:string, middleware:list<string>}|null,
     *     1: array<string, string>
     * }
     */
    private function resolve(string $method, string $uri): array
    {
        $methodRoutes = $this->routes[$method] ?? [];

        if (isset($methodRoutes[$uri])) {
            return [$methodRoutes[$uri], []];
        }

        foreach ($methodRoutes as $path => $route) {
            $pattern = $this->buildPattern($path);
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                return [$route, $params];
            }
        }

        return [null, []];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
