<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Estoque mínimo atingido (disponível <= mínimo). Alimenta a lista de reposição. */
final readonly class MinimumStockReached implements DomainEvent
{
    public function __construct(
        public string $itemId,
        public string $sku,
        public string $available,
        public string $minimum,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
