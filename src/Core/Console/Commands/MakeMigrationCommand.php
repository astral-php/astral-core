<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;

/**
 * Commande : make:migration
 *
 * Génère un fichier de migration horodaté dans database/migrations/.
 * Détecte automatiquement si la migration crée une table (préfixe create_*_table)
 * et adapte le stub en conséquence.
 *
 * Usage :
 *   php bin/console make:migration create_articles_table
 *   php bin/console make:migration add_slug_to_articles_table
 */
final class MakeMigrationCommand implements CommandInterface
{
    public function __construct(private string $migrationsPath) {}

    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Génère un nouveau fichier de migration horodaté';
    }

    public function execute(array $args, Console $console): int
    {
        $name = $args[0] ?? null;

        if ($name === null || $name === '') {
            $console->error('Usage : make:migration <nom_en_snake_case>');
            $console->writeln('');
            $console->writeln('  Exemples :');
            $console->writeln('    <comment>php bin/console make:migration create_articles_table</comment>');
            $console->writeln('    <comment>php bin/console make:migration add_slug_to_articles_table</comment>');
            return 1;
        }

        $name = strtolower(preg_replace('/\s+/', '_', trim($name)));

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            $console->error("Nom invalide : utilisez uniquement des lettres minuscules, chiffres et underscores.");
            return 1;
        }

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename  = "{$timestamp}_{$name}.php";
        $className = $this->toClassName($name);
        $path      = $this->migrationsPath . DIRECTORY_SEPARATOR . $filename;

        $isCreateTable = preg_match('/^create_(.+)_table$/', $name, $m);
        $tableName     = $isCreateTable ? $m[1] : 'nom_de_la_table';

        $stub = $isCreateTable
            ? $this->stubCreateTable($className, $tableName)
            : $this->stubGeneric($className);

        file_put_contents($path, $stub);

        $console->success("Migration créée : database/migrations/{$filename}");
        $console->writeln('');
        $console->writeln("  Classe : <info>{$className}</info>");
        $console->writeln("  Éditez les méthodes <comment>up()</comment> et <comment>down()</comment> selon vos besoins.");

        return 0;
    }

    // -------------------------------------------------------------------------
    // Stubs
    // -------------------------------------------------------------------------

    private function stubCreateTable(string $className, string $table): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Database\Migration\Migration;

        /**
         * Migration : création de la table `{$table}`.
         */
        class {$className} extends Migration
        {
            public function up(PDO \$pdo): void
            {
                \$driver = \$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

                if (\$driver === 'sqlite') {
                    \$pdo->exec(<<<'SQL'
                        CREATE TABLE IF NOT EXISTS {$table} (
                            id         INTEGER  PRIMARY KEY,
                            created_at TEXT     NOT NULL DEFAULT (datetime('now'))
                        )
                    SQL);
                } else {
                    \$pdo->exec(<<<'SQL'
                        CREATE TABLE IF NOT EXISTS `{$table}` (
                            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                            `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    SQL);
                }
            }

            public function down(PDO \$pdo): void
            {
                \$pdo->exec('DROP TABLE IF EXISTS {$table}');
            }
        }
        PHP;
    }

    private function stubGeneric(string $className): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Database\Migration\Migration;

        /**
         * Migration : {$className}.
         */
        class {$className} extends Migration
        {
            public function up(PDO \$pdo): void
            {
                // TODO : appliquer les changements de schéma
                // Ex : \$pdo->exec('ALTER TABLE ma_table ADD COLUMN colonne TEXT NULL');
            }

            public function down(PDO \$pdo): void
            {
                // TODO : annuler les changements de schéma
                // Ex : \$pdo->exec('ALTER TABLE ma_table DROP COLUMN colonne');
            }
        }
        PHP;
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function toClassName(string $snake): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snake)));
    }
}
