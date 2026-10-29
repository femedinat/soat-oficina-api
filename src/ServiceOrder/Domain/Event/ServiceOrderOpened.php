<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Ordem de Serviço Aberta (cliente e veículo identificados). */
final readonly class ServiceOrderOpened implements DomainEvent
{
    public function __construct(
        public string $orderId,
        public string $customerId,
        public string $vehicleId,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
