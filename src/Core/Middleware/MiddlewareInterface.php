<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Request;

/**
 * Contrat d'un middleware HTTP.
 *
 * Chaque middleware reçoit la requête courante et un callable $next
 * qu'il doit appeler pour passer au maillon suivant du pipeline.
 * La valeur retournée par $next() (une Response ou null) doit être
 * propagée pour que le Router puisse l'envoyer au client.
 *
 * Exemple :
 *   public function handle(Request $request, callable $next): mixed
 *   {
 *       // logique avant le contrôleur
 *       $response = $next();
 *       // logique après (optionnel)
 *       return $response;
 *   }
 */
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed;
}
