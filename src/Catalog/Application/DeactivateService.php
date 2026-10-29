<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;

/** Caso de uso: inativar serviço (exclusão lógica; OS antigas continuam com o histórico). */
final readonly class DeactivateService
{
    public function __construct(private LockedServiceCommand $command) {}

    public function execute(string $id): ServiceView
    {
        return $this->command->run($id, static fn (Service $service) => $service->deactivate());
    }
}
