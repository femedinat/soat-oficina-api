<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Inventory\Domain\Exception\SkuAlreadyRegisteredException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemFilter;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\Sku;
use Oficina\Inventory\Domain\StockMovement;
use Oficina\Shared\Application\Page;

/** Repositório em memória para testes unitários dos casos de uso (guarda cópias, como um banco). */
final class InMemoryInventoryItemRepository implements InventoryItemRepository
{
    /** @var array<string, InventoryItem> */
    private array $items = [];

    /** @var list<StockMovement> */
    public array $movements = [];

    /** @var list<string> ids bloqueados, na ordem pedida ao "banco" */
    public array $lockedIds = [];

    public function save(InventoryItem $item): void
    {
        foreach ($this->items as $stored) {
            if ($stored->sku()->equals($item->sku()) && ! $stored->id->equals($item->id)) {
                throw new SkuAlreadyRegisteredException;
            }
        }

        array_push($this->movements, ...$item->pullNewMovements());
        $stored = clone $item;
        $stored->pullEvents(); // como no banco: o que é carregado de novo não traz eventos pendentes
        $this->items[$item->id->value] = $stored;
    }

    public function findById(InventoryItemId $id): ?InventoryItem
    {
        return isset($this->items[$id->value]) ? clone $this->items[$id->value] : null;
    }

    public function findBySku(Sku $sku): ?InventoryItem
    {
        foreach ($this->items as $item) {
            if ($item->sku()->equals($sku)) {
                return clone $item;
            }
        }

        return null;
    }

    public function lockForUpdate(array $ids): array
    {
        $values = array_map(static fn (InventoryItemId $id): string => $id->value, $ids);
        sort($values);
        array_push($this->lockedIds, ...$values);

        $result = [];
        foreach ($values as $value) {
            if (isset($this->items[$value])) {
                $result[$value] = clone $this->items[$value];
            }
        }

        return $result;
    }

    public function paginate(int $page, int $perPage, InventoryItemFilter $filter): Page
    {
        $matches = array_values(array_filter($this->items, static fn (InventoryItem $item): bool => ($filter->includeInactive || $item->isActive())
            && ($filter->type === null || $item->type() === $filter->type)
            && ($filter->search === null || stripos($item->name().' '.$item->sku()->value, $filter->search) !== false)));
        usort($matches, static fn (InventoryItem $a, InventoryItem $b): int => strcmp($a->name(), $b->name()));

        return new Page(array_slice($matches, ($page - 1) * $perPage, $perPage), count($matches), $page, $perPage);
    }

    public function belowMinimum(): array
    {
        return array_values(array_filter($this->items, static fn (InventoryItem $item): bool => $item->isActive() && $item->isBelowMinimum()));
    }

    public function movementsOf(InventoryItemId $id, int $page, int $perPage): Page
    {
        $mine = array_reverse(array_values(array_filter($this->movements, static fn (StockMovement $m): bool => $m->itemId->equals($id))));

        return new Page(array_slice($mine, ($page - 1) * $perPage, $perPage), count($mine), $page, $perPage);
    }
}
