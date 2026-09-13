<?php

declare(strict_types=1);

namespace Core\Auth\Middleware;

use Core\Auth\Auth;
use Core\Http\RedirectResponse;
use Core\Middleware\MiddlewareInterface;
use Core\Request;

/**
 * Middleware : utilisateur doit être connecté.
 *
 * Redirige vers /login si l'utilisateur n'est pas authentifié.
 *
 * Usage dans config/routes.php :
 *   $router->get('/profile', ProfileController::class, 'index')
 *          ->middleware(AuthMiddleware::class);
 *
 *   $router->group('/account', function (Router $r) { ... }, [AuthMiddleware::class]);
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth) {}

    public function handle(Request $request, callable $next): mixed
    {
        if (!$this->auth->check()) {
            return RedirectResponse::to('/login');
        }

        return $next();
    }
}
