<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use DateTimeImmutable;

/** Linha do histórico de status: base do acompanhamento pelo cliente e do tempo médio de execução. */
final readonly class StatusChange
{
    public function __construct(
        public ServiceOrderStatus $status,
        public DateTimeImmutable $occurredAt,
    ) {}
}
