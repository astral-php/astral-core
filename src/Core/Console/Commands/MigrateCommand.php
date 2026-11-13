<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Closure;
use Core\Console\CommandInterface;
use Core\Console\Console;
use Database\Exception\DatabaseException;
use Database\Migration\Migrator;

/**
 * Commande : migrate
 *
 * @param Migrator|Closure(): Migrator $migrator
 */
final class MigrateCommand implements CommandInterface
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
        return 'migrate';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Exécute toutes les migrations en attente';
    }

    #[\Override]
    public function execute(array $args, Console $console): int
    {
        $console->info('Exécution des migrations…');
        $console->writeln('');

        try {
            $executed = $this->resolve()->run();
        } catch (DatabaseException $e) {
            $console->error($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $console->error('Connexion base de données impossible : ' . $e->getMessage());
            return 1;
        }

        if ($executed === []) {
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

    private function resolve(): Migrator
    {
        return $this->migrator instanceof Migrator ? $this->migrator : ($this->migrator)();
    }
}
