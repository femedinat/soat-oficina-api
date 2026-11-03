<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Veículo Entregue ao Cliente / OS Concluída. */
final readonly class VehicleDelivered implements DomainEvent
{
    public function __construct(
        public string $orderId,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
