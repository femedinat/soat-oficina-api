<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Orçamento devolvido para revisão (OS volta ao diagnóstico). */
final readonly class BudgetReturnedToDiagnosis implements DomainEvent
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
