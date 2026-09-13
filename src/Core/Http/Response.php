<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Réponse HTTP générique.
 *
 * Encapsule le code HTTP, les en-têtes et le corps de la réponse.
 * L'envoi effectif n'est déclenché qu'à l'appel de send(), ce qui
 * rend les contrôleurs testables sans déclencher de sortie.
 *
 * Usage :
 *   return Response::html('<h1>OK</h1>');
 *   return Response::html($html, 201);
 */
class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private string $content    = '',
        private int    $statusCode = 200,
    ) {}

    // -------------------------------------------------------------------------
    // Fabriques
    // -------------------------------------------------------------------------

    public static function html(string $content, int $status = 200): static
    {
        return new static($content, $status);
    }

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function setStatus(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function addHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Getters (utiles pour les tests)
    // -------------------------------------------------------------------------

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    // -------------------------------------------------------------------------
    // Envoi
    // -------------------------------------------------------------------------

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->content;
    }
}
