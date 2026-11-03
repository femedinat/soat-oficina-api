<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Serviço Adicionado à OS. */
final readonly class ServiceAddedToOrder implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $serviceId,
        public int $priceCents,
        public bool $additional,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
