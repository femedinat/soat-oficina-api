<?php

declare(strict_types=1);

namespace Oficina\Shared\Infrastructure;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Domain\Event\DomainEvent;

/**
 * Entrega os eventos no dispatcher do Laravel somente depois do commit:
 * se a transação for desfeita, nenhum consumidor reage a um fato que não aconteceu.
 */
final readonly class LaravelEventPublisher implements EventPublisher
{
    public function __construct(
        private Dispatcher $dispatcher,
        private Connection $connection,
    ) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->connection->afterCommit(fn () => $this->dispatcher->dispatch($event));
        }
    }
}
