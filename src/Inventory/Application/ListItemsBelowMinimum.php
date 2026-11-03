<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemRepository;

/** Caso de uso: lista de reposição (itens ativos com disponível <= mínimo). */
final readonly class ListItemsBelowMinimum
{
    public function __construct(private InventoryItemRepository $items) {}

    /** @return list<InventoryItemView> */
    public function execute(): array
    {
        return array_map(
            static fn (InventoryItem $item): InventoryItemView => InventoryItemView::fromEntity($item),
            $this->items->belowMinimum(),
        );
    }
}
