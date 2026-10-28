<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Uuid;

/** Identidade do agregado Peça/Insumo (UUID v4). */
final readonly class InventoryItemId
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
            throw new InvalidValueException('Identificador de peça/insumo inválido.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
