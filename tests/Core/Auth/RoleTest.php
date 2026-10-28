<?php

declare(strict_types=1);

namespace Tests\Core\Auth;

use Core\Auth\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testValues(): void
    {
        $this->assertSame('admin', Role::Admin->value);
        $this->assertSame('user', Role::User->value);
        $this->assertSame('guest', Role::Guest->value);
    }

    public function testAll(): void
    {
        $this->assertSame(['admin', 'user', 'guest'], Role::all());
    }

    public function testIsValid(): void
    {
        $this->assertTrue(Role::isValid('admin'));
        $this->assertFalse(Role::isValid('superadmin'));
    }
}
