<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\Quantity;

/** Caso de uso: ajustar estoque para a quantidade física contada no inventário (motivo obrigatório). */
final readonly class AdjustStock
{
    public function __construct(private LockedItemCommand $command) {}

    public function execute(string $id, string $countedQuantity, string $reason): InventoryItemView
    {
        $counted = Quantity::of($countedQuantity);

        return $this->command->run($id, static fn (InventoryItem $item) => $item->adjust($counted, $reason));
    }
}
