<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Oficina\Shared\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function test_generates_valid_unique_v4(): void
    {
        $a = Uuid::v4();

        self::assertTrue(Uuid::isValid($a));
        self::assertSame('4', $a[14]);
        self::assertNotSame($a, Uuid::v4());
        self::assertFalse(Uuid::isValid('nao-e-uuid'));
        self::assertFalse(Uuid::isValid(strtoupper($a)));
    }
}
