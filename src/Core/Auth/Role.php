<?php

declare(strict_types=1);

namespace Core\Auth;

/**
 * Rôles applicatifs (backed enum).
 *
 * Usage :
 *   $auth->is(Role::Admin)
 *   $auth->can(Role::Admin, Role::User)
 *   Role::isValid('admin')
 */
enum Role: string
{
    case Admin = 'admin';
    case User  = 'user';
    case Guest = 'guest';

    /** @return list<string> */
    public static function all(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases()
        );
    }

    public static function isValid(string $role): bool
    {
        return self::tryFrom($role) !== null;
    }
}
