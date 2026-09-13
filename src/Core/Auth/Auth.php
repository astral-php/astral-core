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
 *   $this->auth->check();         // est connecté ?
 *   $this->auth->is(Role::ADMIN); // a exactement ce rôle ?
 *   $this->auth->can(Role::ADMIN, Role::USER); // a l'un de ces rôles ?
 *
 * Usage dans une vue :
 *   <?php if ($auth->check()): ?> Bonjour <?= $auth->name() ?> <?php endif ?>
 *   <?php if ($auth->is(Role::ADMIN)): ?> <a href="/admin">Admin</a> <?php endif ?>
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
     */
    public function login(object $user): void
    {
        session_regenerate_id(true);

        $this->session->set(self::SESSION_KEY, [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role ?? Role::USER,
        ]);
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        session_regenerate_id(true);
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
     *   $auth->is(Role::ADMIN)
     */
    public function is(string $role): bool
    {
        return $this->role() === $role;
    }

    /**
     * L'utilisateur a-t-il l'un des rôles listés ?
     *
     *   $auth->can(Role::ADMIN, Role::USER)
     */
    public function can(string ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
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

    /** Retourne le rôle courant (Role::GUEST si non connecté). */
    public function role(): string
    {
        return (string) ($this->user()['role'] ?? Role::GUEST);
    }
}
