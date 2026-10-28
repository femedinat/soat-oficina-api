<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Peça/insumo cadastrado. */
final readonly class InventoryItemRegistered implements DomainEvent
{
    public function __construct(
        public string $itemId,
        public string $sku,
        public string $name,
        public string $type,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
