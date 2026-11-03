<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\ValueObject\Money;
use Oficina\Shared\Domain\ValueObject\Uuid;

/** Entidade interna da OS: uma peça/insumo orçada, com preço unitário congelado na inclusão. */
final readonly class PartLine
{
    public function __construct(
        public string $id,
        public string $inventoryItemId,
        public string $sku,
        public string $name,
        public string $unit,
        public Quantity $quantity,
        public Money $unitPrice,
        public bool $additional = false,
        public bool $approved = false,
    ) {}

    public static function quote(string $inventoryItemId, string $sku, string $name, string $unit, Quantity $quantity, Money $unitPrice, bool $additional = false): self
    {
        return new self(Uuid::v4(), $inventoryItemId, $sku, $name, $unit, $quantity, $unitPrice, $additional);
    }

    public function total(): Money
    {
        return $this->unitPrice->multiplyByThousandths($this->quantity->thousandths);
    }

    public function withQuantity(Quantity $quantity): self
    {
        return new self($this->id, $this->inventoryItemId, $this->sku, $this->name, $this->unit, $quantity, $this->unitPrice, $this->additional, $this->approved);
    }

    public function approve(): self
    {
        return new self($this->id, $this->inventoryItemId, $this->sku, $this->name, $this->unit, $this->quantity, $this->unitPrice, $this->additional, true);
    }
}
