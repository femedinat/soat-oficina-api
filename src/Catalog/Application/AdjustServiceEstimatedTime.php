<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;

/** Caso de uso: ajustar tempo estimado de execução (comparado com o tempo médio real das OS). */
final readonly class AdjustServiceEstimatedTime
{
    public function __construct(private LockedServiceCommand $command) {}

    public function execute(string $id, int $minutes): ServiceView
    {
        return $this->command->run($id, static fn (Service $service) => $service->adjustEstimatedTime($minutes));
    }
}
