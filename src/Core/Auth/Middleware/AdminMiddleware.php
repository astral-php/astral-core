<?php

declare(strict_types=1);

namespace Core\Auth\Middleware;

use Core\Auth\Auth;
use Core\Auth\Role;
use Core\Exception\AuthorizationException;
use Core\Http\RedirectResponse;
use Core\Middleware\MiddlewareInterface;
use Core\Request;

/**
 * Middleware : utilisateur doit être connecté ET avoir le rôle admin.
 *
 * Redirige vers /login si non connecté.
 * Lève AuthorizationException (403) si connecté mais sans droit admin.
 *
 * Usage dans config/routes.php :
 *   $router->group('/admin', function (Router $r) {
 *       $r->get('/dashboard', AdminController::class, 'index');
 *   }, [AdminMiddleware::class]);
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth) {}

    public function handle(Request $request, callable $next): mixed
    {
        if (!$this->auth->check()) {
            return RedirectResponse::to('/login');
        }

        if (!$this->auth->is(Role::ADMIN)) {
            throw new AuthorizationException("Accès réservé aux administrateurs.");
        }

        return $next();
    }
}
