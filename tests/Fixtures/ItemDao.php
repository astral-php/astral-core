<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Database\AbstractDao;

/**
 * @extends AbstractDao<Item>
 */
final class ItemDao extends AbstractDao
{
    protected function getTable(): string
    {
        return 'items';
    }

    protected function getModelClass(): ?string
    {
        return Item::class;
    }

    public function createTable(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS items (
                id    INTEGER PRIMARY KEY,
                name  TEXT NOT NULL,
                slug  TEXT NOT NULL UNIQUE,
                category_id INTEGER NULL
            )
        SQL);
    }

    public function createItem(string $name, string $slug, ?int $categoryId = null): int
    {
        return $this->insert([
            'name'        => $name,
            'slug'        => $slug,
            'category_id' => $categoryId,
        ]);
    }

    public function categoryOf(Item $item): ?Category
    {
        return $this->belongsTo(Category::class, 'categories', (int) ($item->category_id ?? 0));
    }

    public function findWithCategory(int $id): ?Item
    {
        /** @var Item|null $item */
        $item = $this->findById($id);
        if ($item === null) {
            return null;
        }

        // Propriété dynamique pour le test de relation
        /** @phpstan-ignore-next-line */
        $item->category = $this->categoryOf($item);

        return $item;
    }
}
