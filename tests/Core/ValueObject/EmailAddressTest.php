<?php

declare(strict_types=1);

namespace Tests\Core\ValueObject;

use Core\ValueObject\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function testAcceptsValidEmailAndNormalizes(): void
    {
        $email = new EmailAddress('  Alice@Example.COM ');

        self::assertSame('alice@example.com', $email->value());
        self::assertSame('alice@example.com', (string) $email);
    }

    public function testEquals(): void
    {
        $a = new EmailAddress('a@example.com');
        $b = new EmailAddress('A@example.com');

        self::assertTrue($a->equals($b));
    }

    public function testRejectsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EmailAddress('not-an-email');
    }

    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EmailAddress('   ');
    }
}
