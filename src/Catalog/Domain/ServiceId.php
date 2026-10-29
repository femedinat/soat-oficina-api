<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Uuid;

final readonly class ServiceId
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
            throw new InvalidValueException('Identificador de serviço inválido.');
        }

        return new self($value);
    }
}
