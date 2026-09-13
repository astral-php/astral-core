<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Http\ApiResponse;
use Core\Request;

/**
 * Middleware d'authentification par token Bearer.
 *
 * Vérifie la présence et la validité du token dans l'en-tête :
 *   Authorization: Bearer <token>
 *
 * Le token est comparé à la clé définie dans config/app.php ('api_key')
 * et chargée depuis la variable d'environnement API_KEY.
 *
 * Si le token est absent ou invalide, retourne HTTP 401 JSON et
 * bloque l'exécution du contrôleur.
 *
 * Enregistrement dans AppServiceProvider :
 *   $container->bind(BearerTokenMiddleware::class,
 *       fn() => new BearerTokenMiddleware($appConfig['api_key'] ?? ''));
 *
 * Utilisation dans config/routes.php :
 *   $router->group('/api/v1', function (Router $r) { ... }, [
 *       CorsMiddleware::class,
 *       BearerTokenMiddleware::class,
 *   ]);
 */
final class BearerTokenMiddleware implements MiddlewareInterface
{
    public function __construct(private string $apiKey) {}

    public function handle(Request $request, callable $next): mixed
    {
        $header = (string) $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return ApiResponse::unauthorized('En-tête Authorization Bearer manquant.');
        }

        $token = substr($header, 7);

        if ($this->apiKey === '' || $token !== $this->apiKey) {
            return ApiResponse::unauthorized('Token invalide.');
        }

        return $next();
    }
}
