<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Orçamento Calculado e Enviado ao Cliente (inicial ou de reparo adicional). */
final readonly class BudgetSent implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public int $totalCents,
        public bool $additional,
        public DateTimeImmutable $approvalDeadline,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
