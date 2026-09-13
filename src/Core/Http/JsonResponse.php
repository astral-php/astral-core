<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Réponse JSON.
 *
 * Usage :
 *   return JsonResponse::make(['id' => 1, 'name' => 'Alice']);
 *   return JsonResponse::make($errors, 422);
 */
final class JsonResponse extends Response
{
    /**
     * @param array<string, mixed> $data
     */
    public static function make(array $data, int $status = 200): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $instance = new self($json, $status);
        $instance->addHeader('Content-Type', 'application/json; charset=utf-8');

        return $instance;
    }
}
