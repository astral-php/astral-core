<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Closure;
use Core\Console\CommandInterface;
use Core\Console\Console;
use Database\Exception\DatabaseException;
use Database\Migration\Migrator;

/**
 * Commande : migrate:status
 *
 * @param Migrator|Closure(): Migrator $migrator
 */
final class MigrateStatusCommand implements CommandInterface
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
        return 'migrate:status';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Affiche l\'état de toutes les migrations';
    }

    #[\Override]
    public function execute(array $args, Console $console): int
    {
        try {
            $status = $this->resolve()->status();
        } catch (DatabaseException $e) {
            $console->error($e->getMessage());
            return 1;
        } catch (\Throwable $e) {
            $console->error('Connexion base de données impossible : ' . $e->getMessage());
            return 1;
        }

        if ($status === []) {
            $console->info('Aucune migration trouvée dans database/migrations/');
            return 0;
        }

        $rows = array_map(fn(array $row): array => [
            $row['migration'],
            $this->formatStatus($row['status']),
            $row['batch'] !== null ? (string) $row['batch'] : '-',
            $row['executed_at'] ?? '-',
        ], $status);

        $console->table(['Migration', 'Statut', 'Batch', 'Exécutée le'], $rows);

        $applied = count(array_filter($status, fn($r) => $r['status'] === 'applied'));
        $pending = count(array_filter($status, fn($r) => $r['status'] === 'pending'));
        $orphan  = count(array_filter($status, fn($r) => $r['status'] === 'orphan'));

        $console->writeln("  Appliquées : <info>{$applied}</info>  |  En attente : <comment>{$pending}</comment>" . ($orphan > 0 ? "  |  Orphelines : <error>{$orphan}</error>" : ''));
        $console->writeln('');

        return 0;
    }

    private function resolve(): Migrator
    {
        return $this->migrator instanceof Migrator ? $this->migrator : ($this->migrator)();
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'applied' => "\033[32mapplied\033[0m",
            'pending' => "\033[33mpending\033[0m",
            'orphan'  => "\033[31morphan\033[0m",
            default   => $status,
        };
    }
}
