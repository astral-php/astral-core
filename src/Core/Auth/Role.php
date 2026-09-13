<?php

declare(strict_types=1);

namespace Core\Auth;

/**
 * Constantes de rôles.
 *
 * Utilisez ces constantes partout pour éviter les fautes de frappe :
 *   $auth->is(Role::ADMIN)
 *   $auth->can(Role::ADMIN, Role::USER)
 */
final class Role
{
    public const ADMIN = 'admin';
    public const USER  = 'user';
    public const GUEST = 'guest';

    /** @return list<string> Tous les rôles valides */
    public static function all(): array
    {
        return [self::ADMIN, self::USER, self::GUEST];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
