<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain;

/** Porta para hash/verificação de senha (implementada na infraestrutura). */
interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $plain, string $hash): bool;

    /** Gasta o mesmo custo de uma verificação real, para não revelar se o e-mail existe (timing attack). */
    public function burn(string $plain): void;
}
