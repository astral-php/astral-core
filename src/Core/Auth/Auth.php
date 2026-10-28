<?php

declare(strict_types=1);

namespace Core\Auth;

use Core\Session;

/**
 * Service d'authentification et d'autorisation.
 *
 * Stocke les données de l'utilisateur connecté dans la session.
 * Disponible dans toutes les vues via la variable $auth.
 *
 * Usage dans un contrôleur :
 *   $this->auth->login($user);
 *   $this->auth->logout();
 *   $this->auth->check();
 *   $this->auth->is(Role::Admin);
 *   $this->auth->can(Role::Admin, Role::User);
 *
 * Usage dans une vue :
 *   <?php if ($auth->check()): ?> Bonjour <?= $auth->name() ?> <?php endif ?>
 *   <?php if ($auth->is(Role::Admin)): ?> <a href="/admin">Admin</a> <?php endif ?>
 */
final class Auth
{
    private const SESSION_KEY = '_auth';

    public function __construct(private Session $session) {}

    // -------------------------------------------------------------------------
    // Connexion / déconnexion
    // -------------------------------------------------------------------------

    /**
     * Connecte l'utilisateur et stocke ses données en session.
     * Régénère l'ID de session pour prévenir la fixation de session.
     *
     * `$user->role` peut être un Role, une string, ou absent (défaut User).
     */
    public function login(object $user): void
    {
        $this->regenerateSessionId();

        $this->session->set(self::SESSION_KEY, [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $this->normalizeRole($user->role ?? Role::User),
        ]);
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->regenerateSessionId();
    }

    // -------------------------------------------------------------------------
    // Vérifications
    // -------------------------------------------------------------------------

    /** L'utilisateur est-il connecté ? */
    public function check(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    /** L'utilisateur est-il un invité (non connecté) ? */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * L'utilisateur a-t-il exactement ce rôle ?
     *
     *   $auth->is(Role::Admin)
     *   $auth->is('admin')
     */
    public function is(Role|string $role): bool
    {
        return $this->role() === $this->normalizeRole($role);
    }

    /**
     * L'utilisateur a-t-il l'un des rôles listés ?
     *
     *   $auth->can(Role::Admin, Role::User)
     *   $auth->can('admin', 'user')
     */
    public function can(Role|string ...$roles): bool
    {
        $current = $this->role();

        foreach ($roles as $role) {
            if ($current === $this->normalizeRole($role)) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Données de l'utilisateur connecté
    // -------------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        /** @var array<string, mixed>|null */
        return $this->session->get(self::SESSION_KEY);
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public function name(): string
    {
        return (string) ($this->user()['name'] ?? '');
    }

    public function email(): string
    {
        return (string) ($this->user()['email'] ?? '');
    }

    /** Retourne le rôle courant (valeur string ; Guest si non connecté). */
    public function role(): string
    {
        return (string) ($this->user()['role'] ?? Role::Guest->value);
    }

    private function normalizeRole(Role|string $role): string
    {
        return $role instanceof Role ? $role->value : $role;
    }

    /** No-op en CLI / sans session native (aligné Session::start). */
    private function regenerateSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
