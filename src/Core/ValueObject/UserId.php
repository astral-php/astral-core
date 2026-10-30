<?php

declare(strict_types=1);

namespace Core\ValueObject;

use InvalidArgumentException;

/**
 * Identifiant utilisateur strictement positif (value object immuable, opt-in).
 *
 * N’est pas exigé par Auth::id() — utile pour typifier les frontières métier.
 */
final readonly class UserId
{
    private int $value;

    public function __construct(int $id)
    {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('UserId invalide : %d (attendu ≥ 1).', $id));
        }

        $this->value = $id;
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
