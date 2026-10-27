<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\StockMovement;
use Oficina\Shared\Application\Page;

/** Caso de uso: extrato de movimentações de um item (mais recentes primeiro). */
final readonly class ListStockMovements
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(private InventoryItemRepository $items) {}

    /** @return Page<StockMovementView> */
    public function execute(string $id, int $page = 1, int $perPage = 30): Page
    {
        $itemId = InventoryItemId::from($id);

        if ($this->items->findById($itemId) === null) {
            throw new InventoryItemNotFoundException;
        }

        $result = $this->items->movementsOf($itemId, max(1, $page), min(max(1, $perPage), self::MAX_PER_PAGE));

        return new Page(
            array_map(static fn (StockMovement $movement): StockMovementView => StockMovementView::fromEntity($movement), $result->items),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }
}
