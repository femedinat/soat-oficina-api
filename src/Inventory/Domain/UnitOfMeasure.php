<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Unidade de medida do estoque. Só unidade (UN) exige quantidade inteira; as demais aceitam fração. */
enum UnitOfMeasure: string
{
    case UNIT = 'UN';
    case LITER = 'L';
    case KILOGRAM = 'KG';
    case METER = 'M';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtoupper(trim($value)))
            ?? throw new InvalidValueException('Unidade de medida inválida: use UN, L, KG ou M.');
    }

    public function allowsFraction(): bool
    {
        return $this !== self::UNIT;
    }

    public function accepts(Quantity $quantity): bool
    {
        return $this->allowsFraction() || $quantity->isWhole();
    }

    public function label(): string
    {
        return match ($this) {
            self::UNIT => 'Unidade',
            self::LITER => 'Litro',
            self::KILOGRAM => 'Quilograma',
            self::METER => 'Metro',
        };
    }
}
