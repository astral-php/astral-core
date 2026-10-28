<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Cache fichier simple.
 *
 * Stocke des valeurs PHP sérialisées dans un répertoire dédié (hors racine web).
 * Chaque entrée a une durée de vie (TTL en secondes, strictement positif).
 *
 * `has()` est indépendant de la valeur : une entrée valide avec `null` est un hit.
 * `unserialize` refuse les classes (frontière de confiance).
 *
 * `remember()` est best-effort : LOCK_EX protège l'écriture, pas le cycle
 * get → callback → set (deux processus peuvent exécuter le callback).
 *
 * Usage :
 *   $users = $cache->remember('users.all', 3600, fn() => $dao->findAll());
 *   $cache->forget('users.all');
 *   $cache->flush();
 */
final class Cache
{
    public function __construct(private string $cacheDir) {}

    // -------------------------------------------------------------------------
    // API principale
    // -------------------------------------------------------------------------

    /**
     * Retourne la valeur mise en cache si l'entrée est valide,
     * sinon exécute $callback, stocke et retourne le résultat.
     *
     * @throws InvalidArgumentException si $ttl < 1
     * @throws RuntimeException si le répertoire ou l'écriture échoue
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Lit une entrée du cache. Retourne $default si absente, invalide ou expirée.
     * Un hit peut retourner null : utiliser has() pour distinguer miss / hit-null.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->readValidEntry($key);

        if ($entry === null) {
            return $default;
        }

        return $entry['value'];
    }

    /**
     * Écrit une entrée dans le cache avec un TTL en secondes (≥ 1).
     *
     * @throws InvalidArgumentException si $ttl < 1
     * @throws RuntimeException si mkdir ou l'écriture échoue
     */
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->assertPositiveTtl($ttl);
        $this->ensureDir();

        $payload = serialize([
            'expires_at' => time() + $ttl,
            'value'      => $value,
        ]);

        $written = file_put_contents($this->path($key), $payload, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException(sprintf(
                'Unable to write cache file for key "%s".',
                $key
            ));
        }
    }

    /** Vérifie si une entrée existe, est valide et n'est pas expirée (y compris value null). */
    public function has(string $key): bool
    {
        return $this->readValidEntry($key) !== null;
    }

    /** Supprime une entrée du cache (best-effort). */
    public function forget(string $key): void
    {
        $file = $this->path($key);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Supprime toutes les entrées *.cache du répertoire dédié.
     * Retourne le nombre de fichiers effectivement supprimés.
     */
    public function flush(): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $count = 0;
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');

        foreach ($files ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{expires_at: int, value: mixed}|null
     */
    private function readValidEntry(string $key): ?array
    {
        $file = $this->path($key);

        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        // @ : payload corrompu → warning PHP ; traité comme miss
        $data = @unserialize($raw, ['allowed_classes' => false]);

        if (!$this->isValidPayload($data)) {
            $this->forget($key);

            return null;
        }

        /** @var array{expires_at: int, value: mixed} $data */
        if ($this->containsObject($data['value'])) {
            $this->forget($key);

            return null;
        }

        if ($data['expires_at'] < time()) {
            $this->forget($key);

            return null;
        }

        return $data;
    }

    private function isValidPayload(mixed $data): bool
    {
        return is_array($data)
            && array_key_exists('expires_at', $data)
            && array_key_exists('value', $data)
            && is_int($data['expires_at']);
    }

    /** Refuse les objets (y compris __PHP_Incomplete_Class après allowed_classes false). */
    private function containsObject(mixed $value): bool
    {
        if (is_object($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsObject($item)) {
                return true;
            }
        }

        return false;
    }

    private function assertPositiveTtl(int $ttl): void
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException(
                'TTL must be a positive number of seconds.'
            );
        }
    }

    private function path(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    private function ensureDir(): void
    {
        if (is_dir($this->cacheDir)) {
            return;
        }

        if (!mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            throw new RuntimeException(sprintf(
                'Unable to create cache directory "%s".',
                $this->cacheDir
            ));
        }
    }
}
