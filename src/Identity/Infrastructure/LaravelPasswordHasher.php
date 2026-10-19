<?php

declare(strict_types=1);

namespace Oficina\Identity\Infrastructure;

use Illuminate\Support\Facades\Hash;
use Oficina\Identity\Domain\PasswordHasher;

final class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        return Hash::check($plain, $hash);
    }

    public function burn(string $plain): void
    {
        Hash::make($plain);
    }
}
