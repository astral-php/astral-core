<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Fabrique de réponses JSON structurées pour l'API REST.
 *
 * Toutes les réponses respectent un contrat uniforme :
 *
 *   Succès   → { "data": <mixed> }
 *   Paginé   → { "data": [...], "meta": { total, page, per_page, pages } }
 *   Erreur   → { "error": { "code": "...", "message": "..." } }
 *   Validation → { "error": { "code": "VALIDATION_ERROR", "message": "...", "details": {...} } }
 *
 * Usage dans un AbstractApiController :
 *   return ApiResponse::success($article->toArray());
 *   return ApiResponse::validationError($v->errors());
 *   return ApiResponse::notFound("Article #42 introuvable.");
 */
final class ApiResponse
{
    // -------------------------------------------------------------------------
    // Réponses de succès
    // -------------------------------------------------------------------------

    /**
     * Réponse de succès : HTTP 200 (ou code personnalisé).
     *
     * @param mixed $data  Tableau, objet ou valeur scalaire
     */
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return JsonResponse::make(['data' => $data], $status);
    }

    /**
     * Réponse de création : HTTP 201.
     *
     * @param mixed $data  L'entité nouvellement créée
     */
    public static function created(mixed $data): JsonResponse
    {
        return JsonResponse::make(['data' => $data], 201);
    }

    /**
     * Réponse sans contenu : HTTP 204.
     * Typiquement utilisée après DELETE ou PUT sans retour de donnée.
     */
    public static function noContent(): Response
    {
        return new Response('', 204);
    }

    /**
     * Réponse paginée : HTTP 200.
     *
     * @param list<mixed>           $items       Liste des éléments de la page courante
     * @param array<string, mixed>  $pagination  Tableau retourné par AbstractDao::paginate()
     */
    public static function paginated(array $items, array $pagination): JsonResponse
    {
        return JsonResponse::make([
            'data' => $items,
            'meta' => [
                'total'    => (int) ($pagination['total']    ?? count($items)),
                'page'     => (int) ($pagination['current']  ?? 1),
                'per_page' => (int) ($pagination['per_page'] ?? count($items)),
                'pages'    => (int) ($pagination['pages']    ?? 1),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Réponses d'erreur
    // -------------------------------------------------------------------------

    /**
     * Erreur générique.
     *
     * @param string $code     Code machine lisible (ex: "NOT_FOUND", "UNAUTHORIZED")
     * @param string $message  Message human-readable
     * @param int    $status   Code HTTP (400, 401, 403, 404, 409…)
     */
    public static function error(string $code, string $message, int $status = 400): JsonResponse
    {
        return JsonResponse::make([
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * Erreur de validation : HTTP 422.
     *
     * @param array<string, list<string>> $errors  Résultat de Validator::errors()
     */
    public static function validationError(array $errors): JsonResponse
    {
        return JsonResponse::make([
            'error' => [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Les données soumises sont invalides.',
                'details' => $errors,
            ],
        ], 422);
    }

    /**
     * Ressource introuvable : HTTP 404.
     */
    public static function notFound(string $message = 'Ressource introuvable.'): JsonResponse
    {
        return self::error('NOT_FOUND', $message, 404);
    }

    /**
     * Non authentifié : HTTP 401.
     */
    public static function unauthorized(string $message = 'Token manquant ou invalide.'): JsonResponse
    {
        return self::error('UNAUTHORIZED', $message, 401);
    }

    /**
     * Accès refusé : HTTP 403.
     */
    public static function forbidden(string $message = 'Accès refusé.'): JsonResponse
    {
        return self::error('FORBIDDEN', $message, 403);
    }

    /**
     * Conflit (doublon, état incohérent) : HTTP 409.
     */
    public static function conflict(string $message): JsonResponse
    {
        return self::error('CONFLICT', $message, 409);
    }
}
