<?php

declare(strict_types=1);

namespace Core\Auth\Middleware;

use Core\Auth\Auth;
use Core\Http\RedirectResponse;
use Core\Middleware\MiddlewareInterface;
use Core\Request;

/**
 * Middleware : utilisateur doit être un invité (non connecté).
 *
 * Redirige vers / si l'utilisateur est déjà connecté.
 * À appliquer sur les routes /login et /register.
 *
 * Usage dans config/routes.php :
 *   $router->get('/login', AuthController::class, 'loginForm')
 *          ->middleware(GuestMiddleware::class);
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth) {}

    public function handle(Request $request, callable $next): mixed
    {
        if ($this->auth->check()) {
            return RedirectResponse::to('/');
        }

        return $next();
    }
}
