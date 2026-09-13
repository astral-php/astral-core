<?php

declare(strict_types=1);

namespace Controller;

use Core\Http\JsonResponse;
use Core\Http\RedirectResponse;
use Core\Http\Response;
use Core\Validator;
use Core\View;

/**
 * Contrôleur de base.
 *
 * Les méthodes render(), redirect() et json() retournent désormais
 * des objets Response, ce qui rend les actions testables sans sortie HTTP.
 * Le Router appelle Response::send() après l'exécution de l'action.
 */
abstract class AbstractController
{
    protected View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    // -------------------------------------------------------------------------
    // Réponses
    // -------------------------------------------------------------------------

    /**
     * Rend une vue dans le layout et retourne une Response HTML.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): Response
    {
        $html = $this->view->render($view, $data);
        return Response::html($html);
    }

    /**
     * Retourne une réponse de redirection.
     */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return RedirectResponse::to($url, $status);
    }

    /**
     * Retourne une réponse JSON.
     *
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): JsonResponse
    {
        return JsonResponse::make($data, $status);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Valide un tableau de données selon des règles et retourne le Validator.
     *
     * @param array<string, mixed>               $data
     * @param array<string, string|list<string>> $rules
     */
    protected function validate(array $data, array $rules): Validator
    {
        return Validator::make($data, $rules);
    }
}
