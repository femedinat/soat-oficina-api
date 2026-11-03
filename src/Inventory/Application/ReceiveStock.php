<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\Quantity;

/** Caso de uso: registrar entrada de estoque (compra/recebimento). */
final readonly class ReceiveStock
{
    public function __construct(private LockedItemCommand $command) {}

    /** @param string|null $reference número da nota fiscal, se houver */
    public function execute(string $id, string $quantity, ?string $reference = null, ?string $reason = null): InventoryItemView
    {
        $received = Quantity::of($quantity);

        return $this->command->run($id, static fn (InventoryItem $item) => $item->receive($received, $reference, $reason));
    }
}
