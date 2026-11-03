<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\Event;

/** Agregados registram eventos durante as mudanças; a camada de Aplicação os coleta com pullEvents(). */
trait RecordsEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /** @return list<DomainEvent> */
    public function pullEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    protected function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
