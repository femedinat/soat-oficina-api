<?php

declare(strict_types=1);

namespace Oficina\Catalog\Infrastructure;

use Oficina\Catalog\Application\PartLookup;
use Oficina\Inventory\Application\FindInventoryItem;

/** Adaptador (camada anticorrupção): consulta o Estoque pelo caso de uso dele, nunca pelas tabelas. */
final readonly class InventoryPartLookup implements PartLookup
{
    public function __construct(private FindInventoryItem $findItem) {}

    public function isActive(string $inventoryItemId): bool
    {
        return $this->findItem->execute($inventoryItemId)->active;
    }
}
