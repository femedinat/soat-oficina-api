<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Estoque ajustado (inventário físico). */
final readonly class StockAdjusted implements DomainEvent
{
    public function __construct(
        public string $itemId,
        public string $previousPhysical,
        public string $newPhysical,
        public string $reason,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
