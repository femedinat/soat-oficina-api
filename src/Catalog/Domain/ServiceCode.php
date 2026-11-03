<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Código do serviço no catálogo (ex.: TROCA-OLEO): único, maiúsculo e imutável depois do cadastro. */
final readonly class ServiceCode
{
    private const string PATTERN = '/^[A-Z0-9][A-Z0-9._-]{1,29}$/';

    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $normalized = strtoupper(trim($raw));

        if (! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidValueException('Código de serviço inválido: use de 2 a 30 caracteres (letras, números, ".", "-" ou "_").');
        }

        return new self($normalized);
    }
}
