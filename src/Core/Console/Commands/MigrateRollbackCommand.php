<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Closure;
use Core\Console\CommandInterface;
use Core\Console\Console;
use Database\Exception\DatabaseException;
use Database\Migration\Migrator;

/**
 * Commande : migrate:rollback
 *
 * @param Migrator|Closure(): Migrator $migrator
 */
final class MigrateRollbackCommand implements CommandInterface
{
    /** @var Migrator|Closure(): Migrator */
    private Migrator|Closure $migrator;

    /** @param Migrator|Closure(): Migrator $migrator */
    public function __construct(Migrator|Closure $migrator)
    {
        $this->migrator = $migrator;
    }

    #[\Override]
    public function getName(): string
    {
        return 'migrate:rollback';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Annule les migrations du dernier batch';
    }

    #[\Override]
    public function execute(array $args, Console $console): int
    {
        $console->warning('Annulation du dernier batch de migrations…');
        $console->writeln('');

        try {
            $rolledBack = $this->resolve()->rollback();
        } catch (DatabaseException $e) {
            $console->error($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $console->error('Connexion base de données impossible : ' . $e->getMessage());
            return 1;
        }

        if ($rolledBack === []) {
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

    private function resolve(): Migrator
    {
        return $this->migrator instanceof Migrator ? $this->migrator : ($this->migrator)();
    }
}
