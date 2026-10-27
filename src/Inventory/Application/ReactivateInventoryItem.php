<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;

/** Caso de uso: reativar peça/insumo desativado. */
final readonly class ReactivateInventoryItem
{
    public function __construct(private LockedItemCommand $command) {}

    public function execute(string $id): InventoryItemView
    {
        return $this->command->run($id, static fn (InventoryItem $item) => $item->reactivate());
    }
}
