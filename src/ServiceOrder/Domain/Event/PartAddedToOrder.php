<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Peça/Insumo Adicionada à OS. */
final readonly class PartAddedToOrder implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $inventoryItemId,
        public string $quantity,
        public bool $additional,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
