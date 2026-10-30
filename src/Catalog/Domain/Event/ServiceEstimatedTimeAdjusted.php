<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Tempo estimado de execução ajustado. */
final readonly class ServiceEstimatedTimeAdjusted implements DomainEvent
{
    public function __construct(
        public string $serviceId,
        public int $previousMinutes,
        public int $newMinutes,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
