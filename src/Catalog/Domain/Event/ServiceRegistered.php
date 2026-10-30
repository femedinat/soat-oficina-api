<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Serviço cadastrado (disponível no catálogo de orçamentos). */
final readonly class ServiceRegistered implements DomainEvent
{
    public function __construct(
        public string $serviceId,
        public string $code,
        public string $name,
        public int $laborPriceCents,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
