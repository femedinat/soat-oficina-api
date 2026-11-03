<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Oficina\Inventory\Domain\Exception\SkuAlreadyRegisteredException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemFilter;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Inventory\Domain\MovementType;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\Sku;
use Oficina\Inventory\Domain\StockMovement;
use Oficina\Inventory\Domain\UnitOfMeasure;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Money;

final class EloquentInventoryItemRepository implements InventoryItemRepository
{
    public function save(InventoryItem $item): void
    {
        $movements = $item->pullNewMovements();

        try {
            // Saldo e extrato gravados juntos (savepoint quando já houver transação aberta).
            DB::transaction(function () use ($item, $movements): void {
                $model = InventoryItemModel::query()->find($item->id->value) ?? new InventoryItemModel;
                $model->id = $item->id->value;
                $model->fill([
                    'sku' => $item->sku()->value,
                    'name' => $item->name(),
                    'description' => $item->description(),
                    'manufacturer' => $item->manufacturer(),
                    'type' => $item->type()->value,
                    'unit' => $item->unit()->value,
                    'price_cents' => $item->price()->cents,
                    'physical_quantity' => $item->physicalQuantity()->toDecimal(),
                    'reserved_quantity' => $item->reservedQuantity()->toDecimal(),
                    'minimum_quantity' => $item->minimumQuantity()->toDecimal(),
                    'active' => $item->isActive(),
                ]);
                $model->save();

                if ($movements !== []) {
                    StockMovementModel::query()->insert(array_map(self::movementRow(...), $movements));
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Corrida entre a checagem do caso de uso e o INSERT: o índice único é a última barreira.
            throw new SkuAlreadyRegisteredException;
        }
    }

    public function findById(InventoryItemId $id): ?InventoryItem
    {
        $model = InventoryItemModel::query()->find($id->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function findBySku(Sku $sku): ?InventoryItem
    {
        $model = InventoryItemModel::query()->where('sku', $sku->value)->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function lockForUpdate(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $models = InventoryItemModel::query()
            ->whereIn('id', array_map(static fn (InventoryItemId $id): string => $id->value, $ids))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $items = [];
        foreach ($models as $model) {
            $items[$model->id] = $this->toEntity($model);
        }

        return $items;
    }

    public function paginate(int $page, int $perPage, InventoryItemFilter $filter): Page
    {
        $query = $this->filtered($filter);
        $total = (clone $query)->count();
        $models = $query->orderBy('name')->orderBy('id')->forPage($page, $perPage)->get();

        return new Page(
            $models->map(fn (InventoryItemModel $model): InventoryItem => $this->toEntity($model))->values()->all(),
            $total,
            $page,
            $perPage,
        );
    }

    public function belowMinimum(): array
    {
        return InventoryItemModel::query()
            ->where('active', true)
            ->whereRaw('physical_quantity - reserved_quantity <= minimum_quantity')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (InventoryItemModel $model): InventoryItem => $this->toEntity($model))
            ->values()
            ->all();
    }

    public function movementsOf(InventoryItemId $id, int $page, int $perPage): Page
    {
        $query = StockMovementModel::query()->where('inventory_item_id', $id->value);
        $total = (clone $query)->count();
        $models = $query->orderByDesc('sequence')->forPage($page, $perPage)->get();

        return new Page(
            $models->map(static fn (StockMovementModel $model): StockMovement => new StockMovement(
                $model->id,
                InventoryItemId::from($model->inventory_item_id),
                MovementType::from($model->type),
                Quantity::of($model->quantity),
                Quantity::of($model->physical_after),
                Quantity::of($model->reserved_after),
                $model->reason,
                $model->reference,
                new DateTimeImmutable($model->occurred_at),
            ))->values()->all(),
            $total,
            $page,
            $perPage,
        );
    }

    /** @return Builder<InventoryItemModel> */
    private function filtered(InventoryItemFilter $filter): Builder
    {
        $query = InventoryItemModel::query();

        if (! $filter->includeInactive) {
            $query->where('active', true);
        }

        if ($filter->type !== null) {
            $query->where('type', $filter->type->value);
        }

        if ($filter->search !== null) {
            // Escapa os curingas do LIKE: o termo do usuário é sempre literal.
            $term = '%'.addcslashes($filter->search, '%_\\').'%';
            $query->where(static fn (Builder $q) => $q
                ->where('name', 'ilike', $term)
                ->orWhere('sku', 'ilike', $term)
                ->orWhere('manufacturer', 'ilike', $term));
        }

        return $query;
    }

    /** @return array<string, string|null> */
    private static function movementRow(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'inventory_item_id' => $movement->itemId->value,
            'type' => $movement->type->value,
            'quantity' => $movement->quantity->toDecimal(),
            'physical_after' => $movement->physicalAfter->toDecimal(),
            'reserved_after' => $movement->reservedAfter->toDecimal(),
            'reason' => $movement->reason,
            'reference' => $movement->reference,
            'occurred_at' => $movement->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }

    private function toEntity(InventoryItemModel $model): InventoryItem
    {
        return InventoryItem::reconstitute(
            InventoryItemId::from($model->id),
            Sku::from($model->sku),
            $model->name,
            $model->description,
            $model->manufacturer,
            ItemType::from($model->type),
            UnitOfMeasure::from($model->unit),
            Money::fromCents($model->price_cents),
            Quantity::of($model->physical_quantity),
            Quantity::of($model->reserved_quantity),
            Quantity::of($model->minimum_quantity),
            $model->active,
        );
    }
}
