<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\CsrfGuard;
use Core\Request;

/**
 * Middleware CSRF.
 *
 * À appliquer sur les groupes de routes qui nécessitent
 * une protection contre la falsification de requête.
 *
 * Usage dans config/routes.php :
 *   $router->post('/users', UserController::class, 'store')
 *          ->middleware(CsrfMiddleware::class);
 *
 *   // ou sur un groupe entier :
 *   $router->group('/admin', function(Router $r) { ... }, [CsrfMiddleware::class]);
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private CsrfGuard $csrf) {}

    public function handle(Request $request, callable $next): mixed
    {
        $this->csrf->verifyRequest($request);
        return $next();
    }
}
