<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Reserva recusada por estoque insuficiente. Nada foi reservado (tudo ou nada). */
final readonly class StockReservationRefused implements DomainEvent
{
    public function __construct(
        public string $serviceOrderId,
        /** @var list<array{item_id: string, requested: string, available: string}> */
        public array $shortages,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
