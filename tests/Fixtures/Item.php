<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/** Modèle minimal pour les tests AbstractDao. */
final class Item
{
    public int $id = 0;
    public string $name = '';
    public string $slug = '';
    public ?int $category_id = null;
    public ?Category $category = null;
}
