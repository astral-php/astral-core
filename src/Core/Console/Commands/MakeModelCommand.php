<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;

/**
 * Commande : make:model
 *
 * Génère un modèle de données dans app/Models/.
 *
 * Usage :
 *   php bin/console make:model Article
 *   php bin/console make:model BlogPost
 */
final class MakeModelCommand implements CommandInterface
{
    public function __construct(private string $modelsPath) {}

    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Génère un modèle dans app/Models/';
    }

    public function execute(array $args, Console $console): int
    {
        $name = $args[0] ?? null;

        if ($name === null || $name === '') {
            $console->error('Usage : make:model <NomEnPascalCase>');
            $console->writeln('  Exemple : <comment>php bin/console make:model Article</comment>');
            return 1;
        }

        $name = $this->toPascalCase($name);

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $console->error("Nom invalide : utilisez uniquement des lettres et chiffres (PascalCase).");
            return 1;
        }

        if (!is_dir($this->modelsPath)) {
            mkdir($this->modelsPath, 0755, true);
        }

        $path = $this->modelsPath . DIRECTORY_SEPARATOR . "{$name}.php";

        if (file_exists($path)) {
            $console->warning("Le modèle {$name} existe déjà : app/Models/{$name}.php");
            return 1;
        }

        file_put_contents($path, $this->stub($name));

        $console->success("Modèle créé : app/Models/{$name}.php");
        $console->writeln("  Ajoutez vos propriétés publiques typées dans la classe.");

        return 0;
    }

    // -------------------------------------------------------------------------
    // Stub
    // -------------------------------------------------------------------------

    private function stub(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Models;

        /**
         * Modèle {$name}.
         *
         * Représentation anémique d'un enregistrement en base.
         * Les propriétés publiques typées sont hydratées par PDO::FETCH_CLASS.
         */
        class {$name}
        {
            public int    \$id         = 0;
            public string \$created_at = '';

            public function isPersisted(): bool
            {
                return \$this->id > 0;
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'id'         => \$this->id,
                    'created_at' => \$this->created_at,
                ];
            }
        }
        PHP;
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }
}
