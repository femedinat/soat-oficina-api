<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Preço da mão de obra alterado. Orçamentos já emitidos mantêm o preço congelado. */
final readonly class ServicePriceChanged implements DomainEvent
{
    public function __construct(
        public string $serviceId,
        public int $previousCents,
        public int $newCents,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
