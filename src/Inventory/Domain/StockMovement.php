<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use DateTimeImmutable;
use Oficina\Shared\Domain\ValueObject\Uuid;

/**
 * Lançamento imutável no extrato do item (append-only): o que mudou, por quê, em qual OS ou nota
 * e como ficaram os saldos físico e reservado logo depois. É a trilha de auditoria do estoque.
 */
final readonly class StockMovement
{
    public function __construct(
        public string $id,
        public InventoryItemId $itemId,
        public MovementType $type,
        public Quantity $quantity,
        public Quantity $physicalAfter,
        public Quantity $reservedAfter,
        public ?string $reason,
        public ?string $reference,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function record(
        InventoryItemId $itemId,
        MovementType $type,
        Quantity $quantity,
        Quantity $physicalAfter,
        Quantity $reservedAfter,
        ?string $reason = null,
        ?string $reference = null,
    ): self {
        return new self(Uuid::v4(), $itemId, $type, $quantity, $physicalAfter, $reservedAfter, $reason, $reference, new DateTimeImmutable);
    }
}
