<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;

/**
 * Commande : make:module
 *
 * Orchestrateur de scaffolding : génère en une seule commande
 * le modèle, le DAO, le contrôleur et la migration d'une entité.
 *
 * Mode direct (argument fourni) :
 *   php bin/console make:module Article              ← tout générer
 *   php bin/console make:module Article --api        ← contrôleur API
 *   php bin/console make:module Article --no-migrate ← sans migration
 *
 * Mode interactif (sans argument) :
 *   php bin/console make:module
 */
final class MakeModuleCommand implements CommandInterface
{
    public function __construct(
        private string              $basePath,
        private MakeMigrationCommand $migrationCommand,
    ) {}

    public function getName(): string
    {
        return 'make:module';
    }

    public function getDescription(): string
    {
        return 'Génère un module complet (Model + DAO + Controller + Migration)';
    }

    public function execute(array $args, Console $console): int
    {
        $name = $args[0] ?? null;

        if ($name === null || $name === '') {
            return $this->runInteractive($console);
        }

        // Mode direct
        $name     = $this->toPascalCase($name);
        $isApi    = in_array('--api', $args, true);
        $noMigrate = in_array('--no-migrate', $args, true);

        return $this->generate(
            console:       $console,
            name:          $name,
            genModel:      true,
            genDao:        true,
            genController: true,
            controllerType: $isApi ? 'api' : 'resource',
            genMigration:  !$noMigrate,
        );
    }

    // -------------------------------------------------------------------------
    // Mode interactif
    // -------------------------------------------------------------------------

    private function runInteractive(Console $console): int
    {
        $console->writeln('');
        $console->writeln("\033[1m✦ Astral MVC — Générateur de module\033[0m");
        $console->writeln('');

        // 1. Nom de l'entité
        $rawName = $console->ask('Nom de l\'entité (PascalCase)', 'Article');
        $name    = $this->toPascalCase($rawName);

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $console->error("Nom invalide : utilisez des lettres et chiffres en PascalCase.");
            return 1;
        }

        $console->writeln('');

        // 2. Que générer ?
        $what = $console->choice(
            question: 'Que souhaitez-vous générer ?',
            choices:  [
                'a' => 'Tout (Model + DAO + Controller + Migration)',
                'm' => 'Model seulement',
                'd' => 'DAO seulement',
                'c' => 'Controller seulement',
                'g' => 'Migration seulement',
            ],
            default: 'a',
        );

        $console->writeln('');

        $genModel      = in_array($what, ['a', 'm'], true);
        $genDao        = in_array($what, ['a', 'd'], true);
        $genController = in_array($what, ['a', 'c'], true);
        $genMigration  = in_array($what, ['a', 'g'], true);

        // 3. Type de contrôleur
        $controllerType = 'resource';
        if ($genController) {
            $controllerType = $console->choice(
                question: 'Type de contrôleur',
                choices:  [
                    'resource' => 'Web CRUD (HTML + vues)',
                    'api'      => 'API REST (JSON)',
                    'empty'    => 'Vide (à compléter)',
                ],
                default: 'resource',
            );
            $console->writeln('');
        }

        // 4. Confirmation
        $console->writeln("\033[1mRécapitulatif\033[0m");
        $console->writeln("  Entité        : \033[36m{$name}\033[0m");
        $this->printBool($console, 'Model',      $genModel);
        $this->printBool($console, 'DAO',        $genDao);
        if ($genController) {
            $console->writeln("  Controller    : \033[32m✓\033[0m  ({$controllerType})");
        } else {
            $console->writeln("  Controller    : \033[90m✗\033[0m");
        }
        $this->printBool($console, 'Migration',  $genMigration);
        $console->writeln('');

        if (!$console->confirm('Confirmer la génération ?')) {
            $console->info('Annulé.');
            return 0;
        }

        $console->writeln('');

        return $this->generate(
            console:        $console,
            name:           $name,
            genModel:       $genModel,
            genDao:         $genDao,
            genController:  $genController,
            controllerType: $controllerType,
            genMigration:   $genMigration,
        );
    }

    // -------------------------------------------------------------------------
    // Génération
    // -------------------------------------------------------------------------

    private function generate(
        Console $console,
        string  $name,
        bool    $genModel,
        bool    $genDao,
        bool    $genController,
        string  $controllerType,
        bool    $genMigration,
    ): int {
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $console->error("Nom invalide : utilisez des lettres et chiffres en PascalCase.");
            return 1;
        }

        $generated = [];
        $warnings  = [];

        // Model
        if ($genModel) {
            $result = $this->runSubCommand(
                new MakeModelCommand($this->basePath . '/app/Models'),
                [$name],
                $console,
            );
            if ($result === 0) {
                $generated[] = "app/Models/{$name}.php";
            }
        }

        // DAO
        if ($genDao) {
            $result = $this->runSubCommand(
                new MakeDaoCommand(
                    $this->basePath . '/app/Dao',
                    $this->basePath . '/app/Models',
                ),
                [$name],
                $console,
            );
            if ($result === 0) {
                $generated[] = "app/Dao/{$name}Dao.php";
            }
        }

        // Controller
        if ($genController) {
            $ctrlArgs = [$name];
            if ($controllerType === 'resource') {
                $ctrlArgs[] = '--resource';
            } elseif ($controllerType === 'api') {
                $ctrlArgs[] = '--api';
            }

            $result = $this->runSubCommand(
                new MakeControllerCommand(
                    $this->basePath . '/app/Controllers',
                    $this->basePath . '/app/Dao',
                ),
                $ctrlArgs,
                $console,
            );
            if ($result === 0) {
                $generated[] = "app/Controllers/{$name}Controller.php";
            }
        }

        // Migration
        if ($genMigration) {
            $migrationName = 'create_' . $this->toSnakePlural($name) . '_table';
            $result = $this->runSubCommand(
                $this->migrationCommand,
                [$migrationName],
                $console,
            );
            if ($result === 0) {
                $generated[] = "database/migrations/…_{$migrationName}.php";
            }
        }

        // Résumé
        if (!empty($generated)) {
            $console->writeln('');
            $console->writeln("\033[1mFichiers générés :\033[0m");
            foreach ($generated as $file) {
                $console->writeln("  \033[32m✓\033[0m {$file}");
            }
        }

        // Prochaines étapes
        $console->writeln('');
        $console->writeln("\033[1mProchaines étapes :\033[0m");

        if ($genMigration) {
            $console->writeln("  \033[33m1.\033[0m Éditez la migration et lancez : <comment>php bin/console migrate</comment>");
        }

        $console->writeln("  \033[33m" . ($genMigration ? '2' : '1') . ".\033[0m Enregistrez dans <comment>app/Providers/AppServiceProvider.php</comment> :");
        $table = $this->toSnakePlural($name);
        $entity = lcfirst($name);
        $console->writeln("     <info>\$container->singleton({$name}Dao::class, fn(\$c) => new {$name}Dao(\$c->make(PDO::class)));</info>");
        $console->writeln("     <info>\$container->bind({$name}Controller::class, fn(\$c) => new {$name}Controller(\$c->make(View::class), ...));</info>");

        $step = $genMigration ? 3 : 2;
        $console->writeln("  \033[33m{$step}.\033[0m Ajoutez les routes dans <comment>config/routes.php</comment> :");
        $console->writeln("     <info>\$router->get('/{$table}', {$name}Controller::class, 'index');</info>");
        $console->writeln("     <info>\$router->get('/{$table}/:id', {$name}Controller::class, 'show');</info>");
        $console->writeln('');

        return 0;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Exécute une sous-commande en capturant sa sortie (mode silencieux).
     * Retourne le code de sortie.
     */
    private function runSubCommand(
        CommandInterface $command,
        array $args,
        Console $console,
    ): int {
        return $command->execute($args, $console);
    }

    private function printBool(Console $console, string $label, bool $value): void
    {
        $icon = $value ? "\033[32m✓\033[0m" : "\033[90m✗\033[0m";
        $console->writeln("  " . str_pad($label, 14) . ": {$icon}");
    }

    private function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    private function toSnakePlural(string $name): string
    {
        $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }
        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x')) {
            return $snake . 'es';
        }
        return $snake . 's';
    }
}
