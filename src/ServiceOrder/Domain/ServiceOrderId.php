<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Uuid;

/** UUID v4: não sequencial, então o número da OS não permite adivinhar OS de outros clientes. */
final readonly class ServiceOrderId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::v4());
    }

    public static function from(string $value): self
    {
        $value = strtolower(trim($value));

        if (! Uuid::isValid($value)) {
            throw new InvalidValueException('Identificador de ordem de serviço inválido.');
        }

        return new self($value);
    }
}
