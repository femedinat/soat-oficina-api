<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Telefone brasileiro com DDD (10 ou 11 dígitos), armazenado só com dígitos. */
final readonly class Phone
{
    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $digits = (string) preg_replace('/\D/', '', $raw);

        if (! preg_match('/^[1-9]{2}[2-9]\d{7,8}$/', $digits)) {
            throw new InvalidValueException('Telefone inválido. Informe DDD + número.');
        }

        return new self($digits);
    }
}
