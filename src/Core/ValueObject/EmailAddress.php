<?php

declare(strict_types=1);

namespace Core\ValueObject;

use InvalidArgumentException;

/**
 * Adresse e-mail validée (value object immuable, opt-in).
 *
 * N’est pas exigée par Auth::login() ni Mailer::send() — les apps
 * peuvent l’utiliser pour valider / typifier leurs frontières.
 */
final readonly class EmailAddress
{
    private string $value;

    public function __construct(string $email)
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('Adresse e-mail invalide : "%s".', $email));
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
