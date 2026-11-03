<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

/** Quantidade de uma peça/insumo reservada para a OS. */
final readonly class ReservationLine
{
    public function __construct(
        public InventoryItemId $itemId,
        public Quantity $quantity,
    ) {}

    /** @return array{item_id: string, quantity: string} */
    public function toArray(): array
    {
        return ['item_id' => $this->itemId->value, 'quantity' => $this->quantity->toDecimal()];
    }
}
