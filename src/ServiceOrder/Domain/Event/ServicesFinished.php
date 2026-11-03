<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Serviços Finalizados: tempo de execução registrado e OS pronta para retirada. */
final readonly class ServicesFinished implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public int $executionSeconds,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
