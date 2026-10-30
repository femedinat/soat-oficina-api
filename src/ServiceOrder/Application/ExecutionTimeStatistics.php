<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;

/**
 * Porta de leitura (read model) para o monitoramento do tempo de execução.
 * Considera só OS com execução finalizada (Finalizada/Entregue); canceladas ficam fora da média.
 */
interface ExecutionTimeStatistics
{
    /** @return array{orders: int, average_seconds: int|null, min_seconds: int|null, max_seconds: int|null, average_estimated_minutes: int|null} */
    public function overall(?DateTimeImmutable $from, ?DateTimeImmutable $to): array;

    /** @return list<array{service_id: string, code: string, name: string, orders: int, average_seconds: int, estimated_minutes: int}> */
    public function perService(?DateTimeImmutable $from, ?DateTimeImmutable $to): array;
}
