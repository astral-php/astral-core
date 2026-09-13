<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Http\Response;
use Core\Request;

/**
 * Middleware CORS (Cross-Origin Resource Sharing).
 *
 * Ajoute les en-têtes Access-Control-* à toutes les réponses de l'API
 * pour autoriser les appels depuis des frontends (SPA, mobile…).
 *
 * Gère également les requêtes de pré-vol OPTIONS (preflight) en retournant
 * immédiatement un HTTP 204 sans passer par le contrôleur.
 *
 * Configuration recommandée dans config/routes.php :
 *   $router->group('/api/v1', function (Router $r) { ... }, [
 *       CorsMiddleware::class,
 *       BearerTokenMiddleware::class,
 *   ]);
 *
 * Pour restreindre l'origine en production, injectez l'origine autorisée
 * dans le constructeur via le ServiceProvider :
 *   new CorsMiddleware(allowedOrigin: 'https://monapp.com')
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private string $allowedOrigin  = '*',
        private string $allowedMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private string $allowedHeaders = 'Content-Type, Authorization, Accept, X-Requested-With',
    ) {}

    public function handle(Request $request, callable $next): mixed
    {
        // ── Requête préflight OPTIONS — répondre immédiatement ────────────────
        if ($request->method === 'OPTIONS') {
            return $this->corsHeaders(new Response('', 204));
        }

        /** @var mixed $response */
        $response = $next();

        if ($response instanceof Response) {
            return $this->corsHeaders($response);
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function corsHeaders(Response $response): Response
    {
        return $response
            ->addHeader('Access-Control-Allow-Origin',  $this->allowedOrigin)
            ->addHeader('Access-Control-Allow-Methods', $this->allowedMethods)
            ->addHeader('Access-Control-Allow-Headers', $this->allowedHeaders);
    }
}
