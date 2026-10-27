<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Preço da peça/insumo alterado. Orçamentos já emitidos mantêm o preço antigo. */
final readonly class InventoryItemPriceChanged implements DomainEvent
{
    public function __construct(
        public string $itemId,
        public int $previousPriceCents,
        public int $newPriceCents,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
