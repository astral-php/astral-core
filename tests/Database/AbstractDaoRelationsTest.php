<?php

declare(strict_types=1);

namespace Tests\Database;

use Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\CategoryDao;
use Tests\Fixtures\ItemDao;

/**
 * Tests d'intégration des helpers hasMany() / belongsTo().
 */
final class AbstractDaoRelationsTest extends TestCase
{
    private PDO $pdo;
    private CategoryDao $categoryDao;
    private ItemDao $itemDao;

    protected function setUp(): void
    {
        Connection::reset();

        $this->pdo = Connection::getInstance([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->categoryDao = new CategoryDao($this->pdo);
        $this->itemDao     = new ItemDao($this->pdo);
        $this->categoryDao->createTable();
        $this->itemDao->createTable();
    }

    protected function tearDown(): void
    {
        Connection::reset();
    }

    public function testHasManyReturnsRelatedItems(): void
    {
        $categoryId = $this->categoryDao->createCategory('Tech', 'tech');
        $this->itemDao->createItem('One', 'one', $categoryId);
        $this->itemDao->createItem('Two', 'two', $categoryId);
        $this->itemDao->createItem('Other', 'other', null);

        $items = $this->categoryDao->itemsOf($categoryId);

        $this->assertCount(2, $items);
        $this->assertSame('One', $items[0]->name);
        $this->assertSame('Two', $items[1]->name);
    }

    public function testBelongsToReturnsParent(): void
    {
        $categoryId = $this->categoryDao->createCategory('News', 'news');
        $itemId     = $this->itemDao->createItem('Headline', 'headline', $categoryId);

        $item = $this->itemDao->findById($itemId);
        $this->assertNotNull($item);

        $category = $this->itemDao->categoryOf($item);
        $this->assertNotNull($category);
        $this->assertSame('News', $category->name);
    }

    public function testFindWithItemsHydratesRelation(): void
    {
        $categoryId = $this->categoryDao->createCategory('Docs', 'docs');
        $this->itemDao->createItem('Guide', 'guide', $categoryId);

        $category = $this->categoryDao->findWithItems($categoryId);

        $this->assertNotNull($category);
        $this->assertCount(1, $category->items);
        $this->assertSame('Guide', $category->items[0]->name);
    }

    public function testFindWithCategoryHydratesRelation(): void
    {
        $categoryId = $this->categoryDao->createCategory('Tips', 'tips');
        $itemId     = $this->itemDao->createItem('Tip', 'tip', $categoryId);

        $item = $this->itemDao->findWithCategory($itemId);

        $this->assertNotNull($item);
        $this->assertNotNull($item->category);
        $this->assertSame('Tips', $item->category->name);
    }

    public function testBelongsToReturnsNullWhenNoForeignKey(): void
    {
        $itemId = $this->itemDao->createItem('Orphan', 'orphan', null);
        $item   = $this->itemDao->findById($itemId);

        $this->assertNotNull($item);
        $this->assertNull($this->itemDao->categoryOf($item));
    }
}
