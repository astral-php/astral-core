<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;
use Database\Exception\DatabaseException;
use Database\Migration\Migrator;

/**
 * Commande : migrate:rollback
 *
 * Annule toutes les migrations du dernier batch (ordre inverse d'application).
 *
 * Usage :
 *   php bin/console migrate:rollback
 */
final class MigrateRollbackCommand implements CommandInterface
{
    public function __construct(private Migrator $migrator) {}

    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Annule les migrations du dernier batch';
    }

    public function execute(array $args, Console $console): int
    {
        $console->warning('Annulation du dernier batch de migrations…');
        $console->writeln('');

        try {
            $rolledBack = $this->migrator->rollback();
        } catch (DatabaseException $e) {
            $console->error($e->getMessage());
            return 1;
        }

        if (empty($rolledBack)) {
            $console->info('Aucune migration à annuler — rien à faire.');
            return 0;
        }

        foreach ($rolledBack as $migration) {
            $console->warning("Annulée : {$migration}");
        }

        $console->writeln('');
        $count = count($rolledBack);
        $console->info("{$count} migration(s) annulée(s).");

        return 0;
    }
}
