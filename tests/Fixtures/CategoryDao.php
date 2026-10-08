<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Database\AbstractDao;

/**
 * @extends AbstractDao<Category>
 */
final class CategoryDao extends AbstractDao
{
    protected function getTable(): string
    {
        return 'categories';
    }

    protected function getModelClass(): ?string
    {
        return Category::class;
    }

    public function createTable(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS categories (
                id   INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE
            )
        SQL);
    }

    public function createCategory(string $name, string $slug): int
    {
        return $this->insert([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    /**
     * @return list<Item>
     */
    public function itemsOf(int $categoryId): array
    {
        return $this->hasMany(Item::class, 'items', 'category_id', $categoryId);
    }

    public function findWithItems(int $id): ?Category
    {
        /** @var Category|null $category */
        $category = $this->findById($id);
        if ($category === null) {
            return null;
        }

        $category->items = $this->itemsOf($id);

        return $category;
    }
}
