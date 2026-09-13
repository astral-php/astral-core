<?php

declare(strict_types=1);

namespace Core;

/**
 * Encapsule la requête HTTP courante.
 *
 * Nouveautés :
 *   - Spoofing de verbe HTTP via le champ caché _method (PUT, PATCH, DELETE)
 *   - Corps JSON automatiquement décodé quand Content-Type: application/json
 *   - Accès aux en-têtes HTTP via header()
 */
final class Request
{
    public string $method;
    public string $uri;

    /** @var array<string, mixed> */
    public array $queryParams;

    /** @var array<string, mixed> */
    public array $body;

    public function __construct()
    {
        $raw               = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->body        = $this->parseBody();
        $this->method      = $this->resolveMethod($raw);
        $this->uri         = $this->parseUri();
        $this->queryParams = $_GET;
    }

    // -------------------------------------------------------------------------
    // Accès aux données
    // -------------------------------------------------------------------------

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Retourne un champ du corps de la requête (POST / JSON),
     * ou l'intégralité du body si aucune clé n'est donnée.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /** Retourne un en-tête HTTP (insensible à la casse). */
    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function isXhr(): bool
    {
        return ($this->header('X-Requested-With') ?? '') === 'XMLHttpRequest';
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Si la méthode brute est POST et que le champ _method est PUT/PATCH/DELETE,
     * retourne le verbe spoofé (HTML forms ne supportent que GET et POST).
     */
    private function resolveMethod(string $raw): string
    {
        if ($raw !== 'POST') {
            return $raw;
        }

        $spoofed = strtoupper((string) ($this->body['_method'] ?? ''));

        if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $spoofed;
        }

        return $raw;
    }

    /** @return array<string, mixed> */
    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '{}';
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?') ?: '/';
        return $uri !== '/' ? rtrim($uri, '/') : '/';
    }
}
