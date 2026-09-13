<?php

declare(strict_types=1);

namespace Database;

use Database\Exception\DatabaseException;
use PDO;
use PDOException;

/**
 * Singleton PDO — bascule SQLite / MySQL via la config.
 *
 * Configuration attendue (tableau associatif) :
 * [
 *   'driver'   => 'sqlite' | 'mysql',
 *   'database' => '/chemin/vers/db.sqlite' | 'nom_de_la_base',
 *   'host'     => 'localhost',   // MySQL uniquement
 *   'port'     => 3306,          // MySQL uniquement
 *   'username' => 'root',        // MySQL uniquement
 *   'password' => '',            // MySQL uniquement
 *   'charset'  => 'utf8mb4',     // MySQL uniquement
 * ]
 */
final class Connection
{
    private static ?PDO $instance = null;

    private function __construct() {}

    /**
     * Retourne l'instance PDO partagée, l'initialise si nécessaire.
     *
     * @param array<string, mixed> $config
     */
    public static function getInstance(array $config = []): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (empty($config)) {
            throw new DatabaseException('Configuration PDO manquante.');
        }

        self::$instance = self::createConnection($config);
        return self::$instance;
    }

    /** Réinitialise la connexion (utile pour les tests). */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $config */
    private static function createConnection(array $config): PDO
    {
        try {
            [$dsn, $username, $password] = match ($config['driver'] ?? 'sqlite') {
                'mysql'  => self::buildMysqlDsn($config),
                'sqlite' => self::buildSqliteDsn($config),
                default  => throw new DatabaseException("Driver non supporté : {$config['driver']}"),
            };

            $pdo = new PDO(
                dsn:      $dsn,
                username: $username,
                password: $password,
                options:  [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // Active les clés étrangères pour SQLite
            if (($config['driver'] ?? 'sqlite') === 'sqlite') {
                $pdo->exec('PRAGMA foreign_keys = ON;');
            }

            return $pdo;
        } catch (PDOException $e) {
            throw new DatabaseException(
                message:  'Connexion PDO échouée : ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /** @return array{string, null, null} */
    private static function buildSqliteDsn(array $config): array
    {
        $database = $config['database'] ?? ':memory:';
        return ["sqlite:{$database}", null, null];
    }

    /** @return array{string, string, string} */
    private static function buildMysqlDsn(array $config): array
    {
        $host    = $config['host']    ?? 'localhost';
        $port    = $config['port']    ?? 3306;
        $dbname  = $config['database'];
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        return [$dsn, $config['username'] ?? 'root', $config['password'] ?? ''];
    }
}
