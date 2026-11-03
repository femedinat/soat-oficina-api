<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: OS Cancelada (rejeição, prazo vencido ou decisão da oficina). */
final readonly class ServiceOrderCanceled implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public string $reason,
        public bool $hadStockReserved,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
