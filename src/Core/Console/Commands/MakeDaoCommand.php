<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;

/**
 * Commande : make:dao
 *
 * Génère un DAO dans app/Dao/ héritant d'AbstractDao.
 * Détecte automatiquement si le modèle correspondant existe dans app/Models/.
 *
 * Usage :
 *   php bin/console make:dao Article
 */
final class MakeDaoCommand implements CommandInterface
{
    public function __construct(
        private string $daoPath,
        private string $modelsPath,
    ) {}

    public function getName(): string
    {
        return 'make:dao';
    }

    public function getDescription(): string
    {
        return 'Génère un DAO dans app/Dao/';
    }

    public function execute(array $args, Console $console): int
    {
        $name = $args[0] ?? null;

        if ($name === null || $name === '') {
            $console->error('Usage : make:dao <NomEnPascalCase>');
            $console->writeln('  Exemple : <comment>php bin/console make:dao Article</comment>');
            return 1;
        }

        $name = $this->toPascalCase($name);

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $console->error("Nom invalide : utilisez uniquement des lettres et chiffres (PascalCase).");
            return 1;
        }

        if (!is_dir($this->daoPath)) {
            mkdir($this->daoPath, 0755, true);
        }

        $path = $this->daoPath . DIRECTORY_SEPARATOR . "{$name}Dao.php";

        if (file_exists($path)) {
            $console->warning("Le DAO {$name}Dao existe déjà : app/Dao/{$name}Dao.php");
            return 1;
        }

        $modelExists = file_exists($this->modelsPath . DIRECTORY_SEPARATOR . "{$name}.php");
        $tableName   = $this->toSnakePlural($name);

        file_put_contents($path, $this->stub($name, $tableName, $modelExists));

        $console->success("DAO créé : app/Dao/{$name}Dao.php");

        if (!$modelExists) {
            $console->warning("Le modèle App\\Models\\{$name} n'existe pas encore.");
            $console->writeln("  → Lancez : <comment>php bin/console make:model {$name}</comment>");
        }

        $console->writeln("  Table cible : <info>{$tableName}</info> (modifiable dans getTable())");

        return 0;
    }

    // -------------------------------------------------------------------------
    // Stub
    // -------------------------------------------------------------------------

    private function stub(string $name, string $table, bool $modelExists): string
    {
        $modelUse   = $modelExists ? "\nuse App\\Models\\{$name};" : '';
        $modelClass = $modelExists
            ? "\n    protected function getModelClass(): string { return {$name}::class; }\n"
            : '';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Dao;
        {$modelUse}
        use Database\AbstractDao;

        /**
         * DAO pour l'entité {$name}.
         *
         * Méthodes héritées disponibles :
         *   findAll(), findById(), findBy(), insert(), update(), delete(), count(), paginate()
         *
         * @extends AbstractDao<\App\Models\\{$name}>
         */
        final class {$name}Dao extends AbstractDao
        {
            protected function getTable(): string { return '{$table}'; }
        {$modelClass}
            // Ajoutez ici vos méthodes métier spécifiques
        }
        PHP;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    /**
     * Convertit PascalCase en snake_case pluriel simple.
     * Ex: Article → articles, BlogPost → blog_posts
     */
    private function toSnakePlural(string $name): string
    {
        $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
        // Pluriel basique
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }
        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x')) {
            return $snake . 'es';
        }
        return $snake . 's';
    }
}
