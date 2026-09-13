<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Réponse de redirection HTTP.
 *
 * Usage :
 *   return RedirectResponse::to('/users');
 *   return RedirectResponse::to('/login', 301);
 */
final class RedirectResponse extends Response
{
    public static function to(string $url, int $status = 302): self
    {
        $instance = new self('', $status);
        $instance->addHeader('Location', $url);

        return $instance;
    }
}
