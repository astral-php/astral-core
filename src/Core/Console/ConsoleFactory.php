<?php

declare(strict_types=1);

namespace Core\Console;

use Core\Console\Commands\ClearCacheCommand;
use Core\Console\Commands\MakeControllerCommand;
use Core\Console\Commands\MakeDaoCommand;
use Core\Console\Commands\MakeMigrationCommand;
use Core\Console\Commands\MakeModelCommand;
use Core\Console\Commands\MakeModuleCommand;
use Core\Console\Commands\MigrateCommand;
use Core\Console\Commands\MigrateRollbackCommand;
use Core\Console\Commands\MigrateStatusCommand;
use Core\Console\Commands\ServeCommand;
use Database\Connection;
use Database\Migration\Migrator;

/**
 * Construction commune de la console Astral (commandes intégrées + app).
 *
 * Les commandes de migration sont toujours enregistrées ; la connexion PDO
 * n'est ouverte qu'à l'exécution (CLI-01 / CLI-02).
 */
final class ConsoleFactory
{
    /**
     * @param list<class-string<CommandInterface>> $appCommandClasses
     */
    public static function create(
        string $basePath,
        string $appName = 'Astral Console',
        array $appCommandClasses = [],
    ): Console {
        $console = new Console($appName);

        $migrationsPath = $basePath . '/database/migrations';
        $makeMigration  = new MakeMigrationCommand($migrationsPath);

        $console->register(new ClearCacheCommand($basePath . '/storage/cache'));
        $console->register(new MakeModelCommand($basePath . '/app/Models'));
        $console->register(new MakeDaoCommand($basePath . '/app/Dao', $basePath . '/app/Models'));
        $console->register(new MakeControllerCommand($basePath . '/app/Controllers', $basePath . '/app/Dao'));
        $console->register($makeMigration);
        $console->register(new MakeModuleCommand($basePath, $makeMigration));
        $console->register(new ServeCommand($basePath));

        $migratorFactory = static function () use ($basePath, $migrationsPath): Migrator {
            /** @var array<string, mixed> $dbConfig */
            $dbConfig = require $basePath . '/config/database.php';
            $pdo = Connection::getInstance($dbConfig);

            return new Migrator($pdo, $migrationsPath);
        };

        $console->register(new MigrateCommand($migratorFactory));
        $console->register(new MigrateRollbackCommand($migratorFactory));
        $console->register(new MigrateStatusCommand($migratorFactory));

        foreach ($appCommandClasses as $class) {
            if (!is_string($class) || !class_exists($class)) {
                throw new \InvalidArgumentException("Commande applicative invalide : {$class}");
            }
            if (!is_subclass_of($class, CommandInterface::class)) {
                throw new \InvalidArgumentException("{$class} doit implémenter CommandInterface.");
            }
            $console->register(new $class());
        }

        return $console;
    }

    /**
     * Charge config/console.php s'il existe.
     *
     * Format attendu :
     *   return [
     *     'commands' => [ \App\Console\FooCommand::class, ... ],
     *   ];
     *
     * @return list<class-string<CommandInterface>>
     */
    public static function loadAppCommands(string $basePath): array
    {
        $file = $basePath . '/config/console.php';
        if (!is_file($file)) {
            return [];
        }

        /** @var mixed $config */
        $config = require $file;
        if (!is_array($config)) {
            return [];
        }

        $commands = $config['commands'] ?? [];
        if (!is_array($commands)) {
            return [];
        }

        $classes = [];
        foreach ($commands as $class) {
            if (is_string($class) && $class !== '') {
                $classes[] = $class;
            }
        }

        /** @var list<class-string<CommandInterface>> $classes */
        return $classes;
    }
}
