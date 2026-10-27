<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;

/** Projeção de leitura (catálogo + saldo) para a camada de Interfaces e para outros contextos. */
final readonly class InventoryItemView
{
    public function __construct(
        public string $id,
        public string $sku,
        public string $name,
        public ?string $description,
        public ?string $manufacturer,
        public string $type,
        public string $unit,
        public int $priceCents,
        public string $price,
        public string $physicalQuantity,
        public string $reservedQuantity,
        public string $availableQuantity,
        public string $minimumQuantity,
        public bool $belowMinimum,
        public bool $active,
    ) {}

    public static function fromEntity(InventoryItem $item): self
    {
        return new self(
            id: $item->id->value,
            sku: $item->sku()->value,
            name: $item->name(),
            description: $item->description(),
            manufacturer: $item->manufacturer(),
            type: $item->type()->value,
            unit: $item->unit()->value,
            priceCents: $item->price()->cents,
            price: $item->price()->toDecimal(),
            physicalQuantity: $item->physicalQuantity()->toDecimal(),
            reservedQuantity: $item->reservedQuantity()->toDecimal(),
            availableQuantity: $item->availableQuantity()->toDecimal(),
            minimumQuantity: $item->minimumQuantity()->toDecimal(),
            belowMinimum: $item->isBelowMinimum(),
            active: $item->isActive(),
        );
    }
}
