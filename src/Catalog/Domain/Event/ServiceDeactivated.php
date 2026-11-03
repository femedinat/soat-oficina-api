<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Serviço inativado: some dos novos orçamentos, histórico das OS mantido. */
final readonly class ServiceDeactivated implements DomainEvent
{
    public function __construct(
        public string $serviceId,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
