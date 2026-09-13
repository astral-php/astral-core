<?php

declare(strict_types=1);

namespace Core;

/**
 * Gestionnaire de session PHP.
 *
 * Encapsule $_SESSION et fournit les messages flash (données
 * lues une seule fois, utiles après un Post-Redirect-Get).
 */
final class Session
{
    private const FLASH_KEY = '_flash';

    private bool $started = false;

    // -------------------------------------------------------------------------
    // Démarrage
    // -------------------------------------------------------------------------

    public function start(): void
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        }
    }

    // -------------------------------------------------------------------------
    // CRUD session
    // -------------------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        session_destroy();
        $_SESSION    = [];
        $this->started = false;
    }

    // -------------------------------------------------------------------------
    // Messages flash (lus une seule fois)
    // -------------------------------------------------------------------------

    /**
     * Enregistre un message flash (succès, erreur, avertissement…).
     *
     * @param mixed $value Chaîne ou tableau de messages.
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_KEY][$key] = $value;
    }

    /**
     * Récupère et supprime un message flash.
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[self::FLASH_KEY][$key] ?? $default;
        unset($_SESSION[self::FLASH_KEY][$key]);
        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION[self::FLASH_KEY][$key]);
    }

    /**
     * Récupère tous les messages flash et les supprime.
     *
     * @return array<string, mixed>
     */
    public function pullAllFlashes(): array
    {
        $flashes = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);
        return $flashes;
    }
}
