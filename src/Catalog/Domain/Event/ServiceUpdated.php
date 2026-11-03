<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Dados do serviço atualizados. */
final readonly class ServiceUpdated implements DomainEvent
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
