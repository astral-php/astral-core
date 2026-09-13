<?php

declare(strict_types=1);

namespace Core;

/**
 * Contrat d'un Service Provider.
 *
 * Un provider regroupe logiquement un ensemble de bindings dans le
 * conteneur DI. Il est déclaré dans config/dependencies.php et
 * instancié automatiquement par Application au démarrage.
 *
 * Exemple :
 *   final class DatabaseServiceProvider implements ServiceProviderInterface
 *   {
 *       public function register(Container $c, array $appConfig, array $dbConfig): void
 *       {
 *           $c->singleton(\PDO::class, fn() => Connection::getInstance($dbConfig));
 *       }
 *   }
 */
interface ServiceProviderInterface
{
    /**
     * Enregistre les bindings de ce provider dans le conteneur.
     *
     * @param array<string, mixed> $appConfig  Tableau issu de config/app.php
     * @param array<string, mixed> $dbConfig   Tableau issu de config/database.php
     */
    public function register(Container $container, array $appConfig, array $dbConfig): void;
}
