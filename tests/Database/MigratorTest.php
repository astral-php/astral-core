<?php

declare(strict_types=1);

namespace Tests\Database;

use Database\Connection;
use Database\Exception\DatabaseException;
use Database\Migration\Migration;
use Database\Migration\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests d'intégration du Migrator.
 * Couvre : ensureTable, run, rollback, status, batches, orphan detection.
 * Utilise SQLite :memory: — aucune base de données réelle requise.
 */
final class MigratorTest extends TestCase
{
    private PDO     $pdo;
    private string  $migrationsDir;
    private Migrator $migrator;

    protected function setUp(): void
    {
        Connection::reset();

        $this->pdo = Connection::getInstance([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        // Répertoire temporaire isolé pour chaque test
        $this->migrationsDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'astral_migrations_' . uniqid();

        mkdir($this->migrationsDir, 0755, true);

        $this->migrator = new Migrator($this->pdo, $this->migrationsDir);
    }

    protected function tearDown(): void
    {
        // Supprime les fichiers de migration créés pour ce test
        foreach (glob($this->migrationsDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->migrationsDir);

        Connection::reset();
    }

    // -------------------------------------------------------------------------
    // Helpers — écriture de fichiers de migration en mémoire
    // -------------------------------------------------------------------------

    /**
     * Écrit un fichier de migration minimal dans le répertoire temporaire.
     * La classe créée a deux méthodes DDL simples (CREATE / DROP TABLE).
     */
    private function writeMigration(string $filename, string $className, string $table): void
    {
        $content = <<<PHP
        <?php
        use Database\Migration\Migration;

        final class {$className} extends Migration
        {
            public function up(\PDO \$pdo): void
            {
                \$pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (id INTEGER PRIMARY KEY, name TEXT)");
            }

            public function down(\PDO \$pdo): void
            {
                \$pdo->exec("DROP TABLE IF EXISTS {$table}");
            }
        }
        PHP;

        file_put_contents(
            $this->migrationsDir . DIRECTORY_SEPARATOR . $filename,
            $content,
        );
    }

    // -------------------------------------------------------------------------
    // ensureTable
    // -------------------------------------------------------------------------

    public function testEnsureTableCreatesTable(): void
    {
        $this->migrator->ensureTable();

        $result = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        $this->assertNotFalse($result->fetchColumn());
    }

    public function testEnsureTableIsIdempotent(): void
    {
        $this->migrator->ensureTable();
        $this->migrator->ensureTable(); // Doit ne pas lever d'exception
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // run
    // -------------------------------------------------------------------------

    public function testRunReturnsEmptyArrayWhenNoMigrations(): void
    {
        $this->assertSame([], $this->migrator->run());
    }

    public function testRunExecutesPendingMigration(): void
    {
        $this->writeMigration('2026_01_01_000001_create_posts_table.php', 'CreatePostsTable', 'posts');

        $executed = $this->migrator->run();

        $this->assertCount(1, $executed);
        $this->assertStringContainsString('create_posts_table', $executed[0]);
    }

    public function testRunCreatesTableInDatabase(): void
    {
        $this->writeMigration('2026_01_01_000001_create_tags_table.php', 'CreateTagsTable', 'tags');
        $this->migrator->run();

        $result = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'");
        $this->assertNotFalse($result->fetchColumn());
    }

    public function testRunDoesNotReExecuteAppliedMigration(): void
    {
        $this->writeMigration('2026_01_01_000001_create_cats_table.php', 'CreateCatsTable', 'cats');

        $first  = $this->migrator->run();
        $second = $this->migrator->run();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
    }

    public function testRunAssignsBatchNumber(): void
    {
        $this->writeMigration('2026_01_01_000001_create_items_table.php', 'CreateItemsTable', 'items');
        $this->migrator->run();

        $batch = $this->pdo->query('SELECT batch FROM migrations')->fetchColumn();
        $this->assertSame('1', (string) $batch);
    }

    public function testRunIncrementsBatchOnSecondRun(): void
    {
        $this->writeMigration('2026_01_01_000001_create_a_table.php', 'CreateATable', 'a');
        $this->migrator->run();

        $this->writeMigration('2026_01_02_000001_create_b_table.php', 'CreateBTable', 'b');
        $this->migrator->run();

        $batches = $this->pdo
            ->query('SELECT DISTINCT batch FROM migrations ORDER BY batch')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['1', '2'], array_map('strval', $batches));
    }

    public function testRunOrdersMigrationsByFilename(): void
    {
        $this->writeMigration('2026_01_02_000001_create_z_table.php', 'CreateZTable', 'z_table');
        $this->writeMigration('2026_01_01_000001_create_a_table2.php', 'CreateATable2', 'a_table');

        $executed = $this->migrator->run();

        $this->assertStringContainsString('create_a_table2', $executed[0]);
        $this->assertStringContainsString('create_z_table', $executed[1]);
    }

    // -------------------------------------------------------------------------
    // rollback
    // -------------------------------------------------------------------------

    public function testRollbackReturnsEmptyWhenNothingApplied(): void
    {
        $this->assertSame([], $this->migrator->rollback());
    }

    public function testRollbackRevertsLastBatch(): void
    {
        $this->writeMigration('2026_01_01_000001_create_pages_table.php', 'CreatePagesTable', 'pages');
        $this->migrator->run();

        $rolledBack = $this->migrator->rollback();

        $this->assertCount(1, $rolledBack);
        $this->assertStringContainsString('create_pages_table', $rolledBack[0]);
    }

    public function testRollbackDropsTableFromDatabase(): void
    {
        $this->writeMigration('2026_01_01_000001_create_notes_table.php', 'CreateNotesTable', 'notes');
        $this->migrator->run();
        $this->migrator->rollback();

        $result = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notes'")
                            ->fetchColumn();
        $this->assertFalse($result);
    }

    public function testRollbackRemovesEntryFromMigrationsTable(): void
    {
        $this->writeMigration('2026_01_01_000001_create_logs_table.php', 'CreateLogsTable', 'logs');
        $this->migrator->run();
        $this->migrator->rollback();

        $count = $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function testRollbackOnlyRevertsLastBatch(): void
    {
        $this->writeMigration('2026_01_01_000001_create_x1_table.php', 'CreateX1Table', 'x1');
        $this->migrator->run();

        $this->writeMigration('2026_01_02_000001_create_x2_table.php', 'CreateX2Table', 'x2');
        $this->migrator->run();

        $this->migrator->rollback(); // Annule batch 2 uniquement

        $count = $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        $this->assertSame(1, (int) $count);
    }

    // -------------------------------------------------------------------------
    // status
    // -------------------------------------------------------------------------

    public function testStatusReturnsEmptyForNoMigrations(): void
    {
        $this->assertSame([], $this->migrator->status());
    }

    public function testStatusMarksMigrationAsApplied(): void
    {
        $this->writeMigration('2026_01_01_000001_create_status_table.php', 'CreateStatusTable', 'status_tbl');
        $this->migrator->run();

        $result = $this->migrator->status();

        $this->assertCount(1, $result);
        $this->assertSame('applied', $result[0]['status']);
        $this->assertSame(1, $result[0]['batch']);
        $this->assertNotNull($result[0]['executed_at']);
    }

    public function testStatusMarksMigrationAsPending(): void
    {
        $this->writeMigration('2026_01_01_000001_create_pending_table.php', 'CreatePendingTable', 'pending_tbl');

        $result = $this->migrator->status();

        $this->assertCount(1, $result);
        $this->assertSame('pending', $result[0]['status']);
        $this->assertNull($result[0]['batch']);
        $this->assertNull($result[0]['executed_at']);
    }

    public function testStatusDetectsOrphanMigration(): void
    {
        // Crée et applique une migration
        $this->writeMigration('2026_01_01_000001_create_orphan_table.php', 'CreateOrphanTable', 'orphan_tbl');
        $this->migrator->run();

        // Supprime le fichier pour simuler l'orphelin
        unlink($this->migrationsDir . DIRECTORY_SEPARATOR . '2026_01_01_000001_create_orphan_table.php');

        $result = $this->migrator->status();

        $orphans = array_filter($result, fn($r) => $r['status'] === 'orphan');
        $this->assertCount(1, $orphans);
    }

    public function testStatusMixedStates(): void
    {
        $this->writeMigration('2026_01_01_000001_create_m1_table.php', 'CreateM1Table', 'm1');
        $this->writeMigration('2026_01_02_000001_create_m2_table.php', 'CreateM2Table', 'm2');

        // On applique seulement la première
        $migrator = new Migrator($this->pdo, $this->migrationsDir);
        $migrator->ensureTable();

        // Applique uniquement m1 manuellement
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS m1 (id INTEGER PRIMARY KEY, name TEXT)");
        $this->pdo->prepare(
            'INSERT INTO migrations (migration, batch, executed_at) VALUES (:m, :b, :e)'
        )->execute([
            ':m' => '2026_01_01_000001_create_m1_table.php',
            ':b' => 1,
            ':e' => date('Y-m-d H:i:s'),
        ]);

        $result   = $this->migrator->status();
        $statuses = array_column($result, 'status');

        $this->assertContains('applied', $statuses);
        $this->assertContains('pending', $statuses);
    }
}
