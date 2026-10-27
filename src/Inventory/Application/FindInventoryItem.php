<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\InventoryItemRepository;

/** Caso de uso: consultar peça/insumo com saldo. */
final readonly class FindInventoryItem
{
    public function __construct(private InventoryItemRepository $items) {}

    public function execute(string $id): InventoryItemView
    {
        $item = $this->items->findById(InventoryItemId::from($id)) ?? throw new InventoryItemNotFoundException;

        return InventoryItemView::fromEntity($item);
    }
}
