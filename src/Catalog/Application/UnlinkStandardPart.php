<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;

/** Caso de uso: desvincular peça/insumo padrão do serviço. */
final readonly class UnlinkStandardPart
{
    public function __construct(private LockedServiceCommand $command) {}

    public function execute(string $id, string $inventoryItemId): ServiceView
    {
        return $this->command->run($id, static fn (Service $service) => $service->unlinkStandardPart($inventoryItemId));
    }
}
