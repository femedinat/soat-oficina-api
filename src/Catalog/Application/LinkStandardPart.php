<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\StandardPart;
use Oficina\Shared\Domain\Exception\DomainException;

/** Caso de uso: vincular peça/insumo padrão ao serviço (sugerida quando o serviço entra numa OS). */
final readonly class LinkStandardPart
{
    public function __construct(
        private LockedServiceCommand $command,
        private PartLookup $parts,
    ) {}

    public function execute(string $id, string $inventoryItemId, string $quantity): ServiceView
    {
        $part = StandardPart::of($inventoryItemId, $quantity);

        if (! $this->parts->isActive($part->inventoryItemId)) {
            throw new DomainException('A peça/insumo está inativa e não pode ser padrão de um serviço.');
        }

        return $this->command->run($id, static fn (Service $service) => $service->linkStandardPart($part));
    }
}
