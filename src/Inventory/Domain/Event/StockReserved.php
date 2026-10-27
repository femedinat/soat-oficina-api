<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Event;

use DateTimeImmutable;
use Oficina\Shared\Domain\Event\DomainEvent;

/** Evento: Estoque reservado para uma OS (orçamento ou reparo adicional aprovado). */
final readonly class StockReserved implements DomainEvent
{
    public function __construct(
        public string $serviceOrderId,
        /** @var list<array{item_id: string, quantity: string}> */
        public array $lines,
        private DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
