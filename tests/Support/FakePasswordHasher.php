<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Identity\Domain\PasswordHasher;

/** Hasher determinístico para testes unitários. */
final class FakePasswordHasher implements PasswordHasher
{
    public int $burned = 0;

    public function hash(string $plain): string
    {
        return 'hash:'.$plain;
    }

    public function verify(string $plain, string $hash): bool
    {
        return $hash === 'hash:'.$plain;
    }

    public function burn(string $plain): void
    {
        $this->burned++;
    }
}
