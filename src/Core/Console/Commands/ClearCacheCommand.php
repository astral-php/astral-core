<?php

declare(strict_types=1);

namespace Core\Console\Commands;

use Core\Console\CommandInterface;
use Core\Console\Console;

/**
 * Commande : cache:clear
 *
 * Supprime tous les fichiers *.cache du répertoire de cache.
 */
final class ClearCacheCommand implements CommandInterface
{
    public function __construct(private string $cacheDir) {}

    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Supprime tous les fichiers du cache fichier';
    }

    public function execute(array $args, Console $console): int
    {
        if (!is_dir($this->cacheDir)) {
            $console->info('Aucun répertoire de cache trouvé — rien à supprimer.');
            return 0;
        }

        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache') ?: [];
        $count = 0;

        foreach ($files as $file) {
            unlink($file);
            $count++;
        }

        if ($count === 0) {
            $console->info('Le cache est déjà vide.');
        } else {
            $console->success("{$count} fichier(s) de cache supprimé(s).");
        }

        return 0;
    }
}
