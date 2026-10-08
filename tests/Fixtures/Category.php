<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/** Modèle parent pour les tests de relations. */
final class Category
{
    public int $id = 0;
    public string $name = '';
    public string $slug = '';

    /** @var list<Item> */
    public array $items = [];
}
