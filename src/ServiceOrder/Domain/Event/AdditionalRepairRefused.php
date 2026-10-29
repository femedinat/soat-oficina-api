<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Reparo Extra Recusado: mantém o escopo original e retoma a execução. */
final readonly class AdditionalRepairRefused implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public bool $byTimeout,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
