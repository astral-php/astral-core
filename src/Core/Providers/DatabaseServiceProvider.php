<?php

declare(strict_types=1);

namespace Core\Providers;

use Core\Container;
use Core\ServiceProviderInterface;
use Database\Connection;

/**
 * Enregistre la connexion PDO (SQLite ou MySQL selon la config).
 *
 * Séparé de FrameworkServiceProvider pour pouvoir être remplacé
 * facilement en test (ex: base en mémoire).
 */
final class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, array $appConfig, array $dbConfig): void
    {
        $container->singleton(\PDO::class, fn() => Connection::getInstance($dbConfig));
    }
}
