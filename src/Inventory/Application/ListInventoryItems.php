<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemFilter;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Shared\Application\Page;

/** Caso de uso: catálogo paginado de peças e insumos (busca por nome, SKU ou fabricante). */
final readonly class ListInventoryItems
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(private InventoryItemRepository $items) {}

    /** @return Page<InventoryItemView> */
    public function execute(
        int $page = 1,
        int $perPage = 15,
        ?string $search = null,
        ?string $type = null,
        bool $includeInactive = false,
    ): Page {
        $search = $search === null ? null : trim($search);
        $filter = new InventoryItemFilter(
            search: $search === '' ? null : mb_substr((string) $search, 0, 100),
            type: $type === null || $type === '' ? null : ItemType::parse($type),
            includeInactive: $includeInactive,
        );

        $result = $this->items->paginate(max(1, $page), min(max(1, $perPage), self::MAX_PER_PAGE), $filter);

        return new Page(
            array_map(static fn (InventoryItem $item): InventoryItemView => InventoryItemView::fromEntity($item), $result->items),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }
}
