<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Peça padrão desvinculada do serviço. */
final readonly class StandardPartUnlinked implements DomainEvent
{
    public function __construct(
        public string $serviceId,
        public string $inventoryItemId,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
