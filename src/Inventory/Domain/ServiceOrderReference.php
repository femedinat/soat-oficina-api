<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Uuid;

/**
 * Referência à Ordem de Serviço (outro contexto). O estoque só guarda o identificador,
 * nunca o agregado da OS: é o que mantém os contextos desacoplados.
 */
final readonly class ServiceOrderReference
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        $value = strtolower(trim($value));

        if (! Uuid::isValid($value)) {
            throw new InvalidValueException('Identificador de ordem de serviço inválido.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
