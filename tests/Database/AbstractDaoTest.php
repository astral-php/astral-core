<?php

declare(strict_types=1);

namespace Tests\Database;

use Database\Connection;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ItemDao;

/**
 * Tests d'intégration du DAO en mémoire (SQLite :memory:).
 */
final class AbstractDaoTest extends TestCase
{
    private PDO $pdo;
    private ItemDao $dao;

    protected function setUp(): void
    {
        Connection::reset();

        $this->pdo = Connection::getInstance([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->dao = new ItemDao($this->pdo);
        $this->dao->createTable();
    }

    protected function tearDown(): void
    {
        Connection::reset();
    }

    public function testInsertAndFindById(): void
    {
        $id   = $this->dao->createItem('Alpha', 'alpha');
        $item = $this->dao->findById($id);

        $this->assertNotNull($item);
        $this->assertSame('Alpha', $item->name);
        $this->assertSame('alpha', $item->slug);
    }

    public function testFindAll(): void
    {
        $this->dao->createItem('Beta', 'beta');
        $this->dao->createItem('Gamma', 'gamma');

        $this->assertCount(2, $this->dao->findAll());
    }

    public function testUpdate(): void
    {
        $id = $this->dao->createItem('Delta', 'delta');
        $this->dao->update($id, ['name' => 'Delta Updated']);

        $item = $this->dao->findById($id);
        $this->assertSame('Delta Updated', $item->name);
    }

    public function testDelete(): void
    {
        $id      = $this->dao->createItem('Epsilon', 'epsilon');
        $deleted = $this->dao->delete($id);

        $this->assertSame(1, $deleted);
        $this->assertNull($this->dao->findById($id));
    }

    public function testCount(): void
    {
        $this->assertSame(0, $this->dao->count());
        $this->dao->createItem('Zeta', 'zeta');
        $this->assertSame(1, $this->dao->count());
    }
}
