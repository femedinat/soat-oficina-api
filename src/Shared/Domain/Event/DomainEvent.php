<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\Event;

use DateTimeImmutable;

/** Fato de negócio já ocorrido (nome no passado), registrado pelo agregado e publicado após o commit. */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
