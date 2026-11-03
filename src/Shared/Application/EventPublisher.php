<?php

declare(strict_types=1);

namespace Oficina\Shared\Application;

use Oficina\Shared\Domain\Event\DomainEvent;

/** Publica eventos de domínio para outros contextos. A implementação só entrega após o commit da transação. */
interface EventPublisher
{
    public function publish(DomainEvent ...$events): void;
}
