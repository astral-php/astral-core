<?php

declare(strict_types=1);

namespace Controller;

use Core\Http\ApiResponse;
use Core\Http\JsonResponse;
use Core\Http\Response;
use Core\Validator;

/**
 * Contrôleur de base pour les APIs JSON.
 *
 * N'hérite PAS d'AbstractController (pas besoin de View).
 * Expose des helpers qui délèguent à ApiResponse pour garantir
 * un format de réponse uniforme dans tous les contrôleurs d'API.
 *
 * Usage :
 *   final class ArticleApiController extends AbstractApiController
 *   {
 *       public function show(string $id): Response
 *       {
 *           $article = $this->articleDao->findById((int) $id);
 *           return $article !== null
 *               ? $this->success($article->toArray())
 *               : $this->notFound("Article #{$id} introuvable.");
 *       }
 *   }
 */
abstract class AbstractApiController
{
    // -------------------------------------------------------------------------
    // Helpers de réponse — succès
    // -------------------------------------------------------------------------

    /**
     * Réponse de succès : HTTP 200.
     *
     * @param mixed $data  Tableau, objet toArray() ou scalaire
     */
    protected function success(mixed $data, int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $status);
    }

    /**
     * Réponse de création : HTTP 201.
     *
     * @param mixed $data  L'entité nouvellement créée
     */
    protected function created(mixed $data): JsonResponse
    {
        return ApiResponse::created($data);
    }

    /**
     * Réponse sans contenu : HTTP 204 (ex: après DELETE).
     */
    protected function noContent(): Response
    {
        return ApiResponse::noContent();
    }

    /**
     * Réponse paginée : HTTP 200 avec bloc meta.
     *
     * @param list<mixed>          $items       Items de la page courante
     * @param array<string, mixed> $pagination  Résultat de AbstractDao::paginate()
     */
    protected function paginated(array $items, array $pagination): JsonResponse
    {
        return ApiResponse::paginated($items, $pagination);
    }

    // -------------------------------------------------------------------------
    // Helpers de réponse — erreurs
    // -------------------------------------------------------------------------

    /**
     * Erreur générique.
     */
    protected function error(string $code, string $message, int $status = 400): JsonResponse
    {
        return ApiResponse::error($code, $message, $status);
    }

    /**
     * Erreur de validation : HTTP 422.
     *
     * @param array<string, list<string>> $errors  Résultat de Validator::errors()
     */
    protected function validationError(array $errors): JsonResponse
    {
        return ApiResponse::validationError($errors);
    }

    /**
     * Ressource introuvable : HTTP 404.
     */
    protected function notFound(string $message = 'Ressource introuvable.'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    /**
     * Non authentifié : HTTP 401.
     */
    protected function unauthorized(string $message = 'Token manquant ou invalide.'): JsonResponse
    {
        return ApiResponse::unauthorized($message);
    }

    /**
     * Conflit : HTTP 409.
     */
    protected function conflict(string $message): JsonResponse
    {
        return ApiResponse::conflict($message);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Valide un tableau de données et retourne le Validator.
     *
     * @param array<string, mixed>               $data
     * @param array<string, string|list<string>> $rules
     */
    protected function validate(array $data, array $rules): Validator
    {
        return Validator::make($data, $rules);
    }
}
