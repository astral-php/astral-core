<?php

declare(strict_types=1);

namespace Tests\Core\Auth;

use Core\Auth\Auth;
use Core\Auth\Role;
use Core\Session;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    private Auth $auth;

    protected function setUp(): void
    {
        $_SESSION = [];
        $session = new Session();
        $session->start();
        $this->auth = new Auth($session);
    }

    public function testLoginAndRoleChecksWithEnum(): void
    {
        $user = (object) [
            'id'    => 1,
            'name'  => 'Alice',
            'email' => 'alice@example.com',
            'role'  => Role::Admin,
        ];

        $this->auth->login($user);

        $this->assertTrue($this->auth->check());
        $this->assertTrue($this->auth->is(Role::Admin));
        $this->assertTrue($this->auth->is('admin'));
        $this->assertTrue($this->auth->can(Role::Admin, Role::User));
        $this->assertSame('admin', $this->auth->role());
        $this->assertSame('Alice', $this->auth->name());
    }

    public function testLoginDefaultsToUserRole(): void
    {
        $user = (object) [
            'id'    => 2,
            'name'  => 'Bob',
            'email' => 'bob@example.com',
        ];

        $this->auth->login($user);

        $this->assertTrue($this->auth->is(Role::User));
        $this->assertSame(Role::User->value, $this->auth->role());
    }

    public function testGuestRoleWhenLoggedOut(): void
    {
        $this->assertTrue($this->auth->guest());
        $this->assertSame(Role::Guest->value, $this->auth->role());
        $this->assertFalse($this->auth->is(Role::Admin));
    }
}
