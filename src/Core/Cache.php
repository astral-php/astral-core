<?php

declare(strict_types=1);

namespace Core;

/**
 * Cache fichier simple.
 *
 * Stocke des valeurs PHP sérialisées dans storage/cache/.
 * Chaque entrée a une durée de vie (TTL en secondes).
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
     * Retourne la valeur mise en cache si valide,
     * sinon exécute $callback, stocke et retourne le résultat.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /** Lit une entrée du cache. Retourne $default si absente ou expirée. */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);

        if (!file_exists($file)) {
            return $default;
        }

        $raw  = file_get_contents($file);
        $data = $raw !== false ? unserialize($raw) : false;

        if ($data === false || !is_array($data) || $data['expires_at'] < time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    /** Écrit une entrée dans le cache avec un TTL en secondes. */
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->ensureDir();

        $payload = serialize([
            'expires_at' => time() + $ttl,
            'value'      => $value,
        ]);

        file_put_contents($this->path($key), $payload, LOCK_EX);
    }

    /** Vérifie si une entrée existe et n'est pas expirée. */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /** Supprime une entrée du cache. */
    public function forget(string $key): void
    {
        $file = $this->path($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Supprime toutes les entrées du cache.
     * Retourne le nombre de fichiers supprimés.
     */
    public function flush(): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }

        $count = 0;
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');

        foreach ($files ?: [] as $file) {
            unlink($file);
            $count++;
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function path(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
}
