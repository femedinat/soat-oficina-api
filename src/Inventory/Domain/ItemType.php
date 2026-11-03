<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Peça (componente do veículo, ex.: pastilha de freio) ou insumo (consumível, ex.: óleo). */
enum ItemType: string
{
    case PART = 'PECA';
    case SUPPLY = 'INSUMO';

    public static function parse(string $value): self
    {
        return self::tryFrom(strtoupper(trim($value)))
            ?? throw new InvalidValueException('Tipo de item inválido: use PECA ou INSUMO.');
    }

    public function label(): string
    {
        return match ($this) {
            self::PART => 'Peça',
            self::SUPPLY => 'Insumo',
        };
    }
}
