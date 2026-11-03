<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\UnitOfMeasure;

/** Caso de uso: atualizar dados cadastrais da peça/insumo. */
final readonly class UpdateInventoryItem
{
    public function __construct(private LockedItemCommand $command) {}

    public function execute(string $id, UpdateInventoryItemInput $input): InventoryItemView
    {
        $type = ItemType::parse($input->type);
        $unit = UnitOfMeasure::parse($input->unit);
        $minimum = Quantity::of($input->minimumQuantity);

        return $this->command->run($id, static fn (InventoryItem $item) => $item->revise(
            $input->name,
            $input->description,
            $input->manufacturer,
            $type,
            $unit,
            $minimum,
        ));
    }
}
