<?php

declare(strict_types=1);

namespace Database\Migration;

use Database\Exception\DatabaseException;
use PDO;
use PDOException;

/**
 * Moteur de migrations de schéma.
 *
 * Responsabilités :
 *   - Créer/maintenir la table `migrations` en base
 *   - Scanner database/migrations/ et résoudre les fichiers en classes
 *   - Exécuter les migrations en attente (run)
 *   - Annuler le dernier batch (rollback)
 *   - Afficher l'état de toutes les migrations (status)
 *
 * Convention de nommage des fichiers :
 *   YYYY_MM_DD_NNNNNN_snake_case_name.php → StudlyCaseName
 *   Ex: 2026_03_11_000001_create_users_table.php → CreateUsersTable
 */
final class Migrator
{
    private const TABLE = 'migrations';

    private bool $tableEnsured = false;

    public function __construct(
        private PDO    $pdo,
        private string $migrationsPath,
    ) {}

    // -------------------------------------------------------------------------
    // API publique
    // -------------------------------------------------------------------------

    /**
     * Exécute toutes les migrations en attente dans un nouveau batch.
     *
     * @return list<string> Noms des fichiers migrés
     * @throws DatabaseException en cas d'erreur d'exécution
     */
    public function run(): array
    {
        $this->ensureTable();
        $pending = $this->getPending();

        if (empty($pending)) {
            return [];
        }

        $batch    = $this->getNextBatch();
        $executed = [];

        foreach ($pending as $file => $class) {
            try {
                $instance = new $class();
                $instance->up($this->pdo);

                $this->pdo->prepare(
                    'INSERT INTO ' . self::TABLE . ' (migration, batch, executed_at)
                     VALUES (:migration, :batch, :executed_at)',
                )->execute([
                    ':migration'   => $file,
                    ':batch'       => $batch,
                    ':executed_at' => date('Y-m-d H:i:s'),
                ]);

                $executed[] = $file;
            } catch (PDOException $e) {
                throw new DatabaseException(
                    message:  "Échec de la migration « {$file} » : " . $e->getMessage(),
                    previous: $e,
                );
            }
        }

        return $executed;
    }

    /**
     * Annule toutes les migrations du dernier batch (ordre inverse).
     *
     * @return list<string> Noms des fichiers annulés
     * @throws DatabaseException en cas d'erreur d'exécution
     */
    public function rollback(): array
    {
        $this->ensureTable();

        $lastBatch = $this->getLastBatch();

        if ($lastBatch === 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT migration FROM ' . self::TABLE . ' WHERE batch = :batch ORDER BY id DESC',
        );
        $stmt->execute([':batch' => $lastBatch]);
        $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $rolledBack = [];

        foreach ($files as $file) {
            $class = $this->resolveClass($file);

            if ($class === null) {
                continue;
            }

            try {
                $instance = new $class();
                $instance->down($this->pdo);

                $this->pdo->prepare(
                    'DELETE FROM ' . self::TABLE . ' WHERE migration = :migration',
                )->execute([':migration' => $file]);

                $rolledBack[] = $file;
            } catch (PDOException $e) {
                throw new DatabaseException(
                    message:  "Échec du rollback « {$file} » : " . $e->getMessage(),
                    previous: $e,
                );
            }
        }

        return $rolledBack;
    }

    /**
     * Retourne l'état de toutes les migrations (appliquées + en attente + orphelines).
     *
     * @return list<array{migration: string, batch: int|null, status: string, executed_at: string|null}>
     */
    public function status(): array
    {
        $this->ensureTable();

        $stmt    = $this->pdo->query('SELECT migration, batch, executed_at FROM ' . self::TABLE . ' ORDER BY id');
        $applied = [];

        foreach ($stmt->fetchAll() as $row) {
            $applied[$row['migration']] = $row;
        }

        $all    = $this->scanFiles();
        $result = [];

        foreach ($all as $file => $class) {
            if (isset($applied[$file])) {
                $result[] = [
                    'migration'   => $file,
                    'batch'       => (int) $applied[$file]['batch'],
                    'status'      => 'applied',
                    'executed_at' => $applied[$file]['executed_at'],
                ];
            } else {
                $result[] = [
                    'migration'   => $file,
                    'batch'       => null,
                    'status'      => 'pending',
                    'executed_at' => null,
                ];
            }
        }

        // Migrations enregistrées en base mais dont le fichier a disparu
        foreach ($applied as $file => $row) {
            if (!isset($all[$file])) {
                $result[] = [
                    'migration'   => $file,
                    'batch'       => (int) $row['batch'],
                    'status'      => 'orphan',
                    'executed_at' => $row['executed_at'],
                ];
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Gestion de la table de suivi
    // -------------------------------------------------------------------------

    /**
     * Crée la table `migrations` si elle n'existe pas encore.
     * Compatible SQLite et MySQL.
     */
    public function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS migrations (
                    id            INTEGER  PRIMARY KEY,
                    migration     TEXT     NOT NULL UNIQUE,
                    batch         INTEGER  NOT NULL DEFAULT 1,
                    executed_at   TEXT     NOT NULL
                )
            SQL);
        } else {
            $this->pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS `migrations` (
                    `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                    `migration`     VARCHAR(255)   NOT NULL,
                    `batch`         INT            NOT NULL DEFAULT 1,
                    `executed_at`   DATETIME       NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `migrations_migration_unique` (`migration`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
        }

        $this->tableEnsured = true;
    }

    // -------------------------------------------------------------------------
    // Helpers internes
    // -------------------------------------------------------------------------

    /**
     * Retourne les migrations en attente (présentes en fichier, absentes en base).
     *
     * @return array<string, string> [filename => classname]
     */
    private function getPending(): array
    {
        $stmt    = $this->pdo->query('SELECT migration FROM ' . self::TABLE);
        $applied = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

        $pending = [];

        foreach ($this->scanFiles() as $file => $class) {
            if (!isset($applied[$file])) {
                $pending[$file] = $class;
            }
        }

        return $pending;
    }

    /**
     * Scanne le dossier des migrations et retourne les fichiers triés par nom.
     *
     * @return array<string, string> [filename => classname]
     */
    private function scanFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        $result = [];

        foreach ($files as $path) {
            $file  = basename($path);
            $class = $this->fileToClass($file);
            require_once $path;
            $result[$file] = $class;
        }

        return $result;
    }

    /**
     * Résout le nom de classe depuis un nom de fichier de migration.
     * Inclut le fichier si nécessaire. Retourne null si le fichier est absent.
     */
    private function resolveClass(string $file): ?string
    {
        $path = $this->migrationsPath . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($path)) {
            return null;
        }

        require_once $path;
        return $this->fileToClass($file);
    }

    /**
     * Convertit un nom de fichier migration en nom de classe StudlyCase.
     *
     * 2026_03_11_000001_create_users_table.php → CreateUsersTable
     */
    private function fileToClass(string $file): string
    {
        $name   = pathinfo($file, PATHINFO_FILENAME);
        $parts  = explode('_', $name, 5);
        $suffix = $parts[4] ?? $name;

        return str_replace(' ', '', ucwords(str_replace('_', ' ', $suffix)));
    }

    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    private function getLastBatch(): int
    {
        $value = $this->pdo->query('SELECT MAX(batch) FROM ' . self::TABLE)->fetchColumn();
        return (int) ($value ?? 0);
    }
}
