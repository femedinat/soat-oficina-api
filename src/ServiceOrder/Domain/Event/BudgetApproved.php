<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Orçamento Aprovado: OS liberada para a oficina e cronômetro iniciado. */
final readonly class BudgetApproved implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public int $totalCents,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
