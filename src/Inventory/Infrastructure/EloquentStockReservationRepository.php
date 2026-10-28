<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use Illuminate\Support\Facades\DB;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\ReservationLine;
use Oficina\Inventory\Domain\ReservationStatus;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\StockReservation;
use Oficina\Inventory\Domain\StockReservationRepository;

final class EloquentStockReservationRepository implements StockReservationRepository
{
    public function lockForServiceOrder(ServiceOrderReference $serviceOrder): ?StockReservation
    {
        $model = StockReservationModel::query()->whereKey($serviceOrder->value)->lockForUpdate()->first();

        return $model === null ? null : $this->toEntity($model);
    }

    public function findForServiceOrder(ServiceOrderReference $serviceOrder): ?StockReservation
    {
        $model = StockReservationModel::query()->find($serviceOrder->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function save(StockReservation $reservation): void
    {
        DB::transaction(function () use ($reservation): void {
            $model = StockReservationModel::query()->find($reservation->serviceOrder->value) ?? new StockReservationModel;
            $model->service_order_id = $reservation->serviceOrder->value;
            $model->status = $reservation->status()->value;
            $model->save();

            // As linhas são o estado atual da reserva: substitui tudo (o histórico fica no extrato).
            StockReservationLineModel::query()->where('service_order_id', $reservation->serviceOrder->value)->delete();
            StockReservationLineModel::query()->insert(array_map(
                static fn (ReservationLine $line): array => [
                    'service_order_id' => $reservation->serviceOrder->value,
                    'inventory_item_id' => $line->itemId->value,
                    'quantity' => $line->quantity->toDecimal(),
                ],
                $reservation->lines(),
            ));
        });
    }

    private function toEntity(StockReservationModel $model): StockReservation
    {
        return StockReservation::reconstitute(
            ServiceOrderReference::from($model->service_order_id),
            ReservationStatus::from($model->status),
            $model->lines()->orderBy('inventory_item_id')->get()->map(
                static fn (StockReservationLineModel $line): ReservationLine => new ReservationLine(
                    InventoryItemId::from($line->inventory_item_id),
                    Quantity::of($line->quantity),
                ),
            )->values()->all(),
        );
    }
}
