<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;

/** Caso de uso: reativar serviço. */
final readonly class ReactivateService
{
    public function __construct(private LockedServiceCommand $command) {}

    public function execute(string $id): ServiceView
    {
        return $this->command->run($id, static fn (Service $service) => $service->reactivate());
    }
}
