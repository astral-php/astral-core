<?php

declare(strict_types=1);

namespace Tests\Core\ValueObject;

use Core\ValueObject\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testAcceptsPositiveId(): void
    {
        $id = new UserId(42);

        self::assertSame(42, $id->toInt());
        self::assertSame('42', (string) $id);
    }

    public function testEquals(): void
    {
        self::assertTrue((new UserId(1))->equals(new UserId(1)));
        self::assertFalse((new UserId(1))->equals(new UserId(2)));
    }

    public function testRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserId(0);
    }

    public function testRejectsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new UserId(-1);
    }
}
