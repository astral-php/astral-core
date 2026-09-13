<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;
use Database\Exception\DatabaseException;
use Database\Migration\Migrator;

/**
 * Commande : migrate
 *
 * Exécute toutes les migrations en attente dans un nouveau batch.
 *
 * Usage :
 *   php bin/console migrate
 */
final class MigrateCommand implements CommandInterface
{
    public function __construct(private Migrator $migrator) {}

    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Exécute toutes les migrations en attente';
    }

    public function execute(array $args, Console $console): int
    {
        $console->info('Exécution des migrations…');
        $console->writeln('');

        try {
            $executed = $this->migrator->run();
        } catch (DatabaseException $e) {
            $console->error($e->getMessage());
            return 1;
        }

        if (empty($executed)) {
            $console->info('Aucune migration en attente — base de données à jour.');
            return 0;
        }

        foreach ($executed as $migration) {
            $console->success("Migrée  : {$migration}");
        }

        $console->writeln('');
        $count = count($executed);
        $console->info("{$count} migration(s) exécutée(s) avec succès.");

        return 0;
    }
}
