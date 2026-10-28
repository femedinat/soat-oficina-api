<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Entrada de estoque registrada. */
final readonly class StockReceived implements DomainEvent
{
    public function __construct(
        public string $itemId,
        public string $quantity,
        public string $physicalAfter,
        public ?string $reference,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
