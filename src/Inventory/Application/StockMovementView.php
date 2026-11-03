<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use DateTimeInterface;
use Oficina\Inventory\Domain\StockMovement;

/** Linha do extrato de movimentações de um item. */
final readonly class StockMovementView
{
    public function __construct(
        public string $id,
        public string $type,
        public string $quantity,
        public string $physicalAfter,
        public string $reservedAfter,
        public ?string $reason,
        public ?string $reference,
        public string $occurredAt,
    ) {}

    public static function fromEntity(StockMovement $movement): self
    {
        return new self(
            id: $movement->id,
            type: $movement->type->value,
            quantity: $movement->quantity->toDecimal(),
            physicalAfter: $movement->physicalAfter->toDecimal(),
            reservedAfter: $movement->reservedAfter->toDecimal(),
            reason: $movement->reason,
            reference: $movement->reference,
            occurredAt: $movement->occurredAt->format(DateTimeInterface::ATOM),
        );
    }
}
