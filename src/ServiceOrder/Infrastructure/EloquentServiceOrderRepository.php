<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Oficina\ServiceOrder\Domain\CancellationReason;
use Oficina\ServiceOrder\Domain\PartLine;
use Oficina\ServiceOrder\Domain\Quantity;
use Oficina\ServiceOrder\Domain\ServiceLine;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\ServiceOrderFilter;
use Oficina\ServiceOrder\Domain\ServiceOrderId;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;
use Oficina\ServiceOrder\Domain\ServiceOrderStatus;
use Oficina\ServiceOrder\Domain\StatusChange;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Money;

final class EloquentServiceOrderRepository implements ServiceOrderRepository
{
    /** Microssegundos preservados: o cronômetro de execução depende das datas exatas. */
    private const string DATE_FORMAT = 'Y-m-d H:i:s.uP';

    private const array RELATIONS = ['serviceLines', 'partLines', 'statusHistory'];

    public function save(ServiceOrder $order): void
    {
        $history = $order->pullNewHistory();

        // OS, itens e histórico gravados juntos (savepoint quando já houver transação aberta).
        DB::transaction(function () use ($order, $history): void {
            $model = ServiceOrderModel::query()->find($order->id->value) ?? new ServiceOrderModel;
            $model->id = $order->id->value;
            $model->fill([
                'customer_id' => $order->customerId,
                'vehicle_id' => $order->vehicleId,
                'status' => $order->status()->value,
                'opened_at' => self::date($order->openedAt()),
                'budget_sent_at' => self::date($order->budgetSentAt()),
                'approval_deadline' => self::date($order->approvalDeadline()),
                'approved_at' => self::date($order->approvedAt()),
                'execution_started_at' => self::date($order->executionStartedAt()),
                'execution_finished_at' => self::date($order->executionFinishedAt()),
                'delivered_at' => self::date($order->deliveredAt()),
                'canceled_at' => self::date($order->canceledAt()),
                'cancellation_reason' => $order->cancellationReason()?->value,
                'awaiting_additional_approval' => $order->isAwaitingAdditionalApproval(),
            ]);
            $model->save();

            // Itens são parte do agregado: regravados junto com ele.
            ServiceOrderServiceModel::query()->where('service_order_id', $order->id->value)->delete();
            ServiceOrderPartModel::query()->where('service_order_id', $order->id->value)->delete();

            $services = [];
            foreach ($order->services() as $position => $line) {
                $services[] = [
                    'id' => $line->id,
                    'service_order_id' => $order->id->value,
                    'service_id' => $line->serviceId,
                    'position' => $position,
                    'code' => $line->code,
                    'name' => $line->name,
                    'price_cents' => $line->price->cents,
                    'estimated_minutes' => $line->estimatedMinutes,
                    'additional' => $line->additional,
                    'approved' => $line->approved,
                ];
            }

            $parts = [];
            foreach ($order->parts() as $position => $line) {
                $parts[] = [
                    'id' => $line->id,
                    'service_order_id' => $order->id->value,
                    'inventory_item_id' => $line->inventoryItemId,
                    'position' => $position,
                    'sku' => $line->sku,
                    'name' => $line->name,
                    'unit' => $line->unit,
                    'quantity' => $line->quantity->toDecimal(),
                    'unit_price_cents' => $line->unitPrice->cents,
                    'additional' => $line->additional,
                    'approved' => $line->approved,
                ];
            }

            if ($services !== []) {
                ServiceOrderServiceModel::query()->insert($services);
            }

            if ($parts !== []) {
                ServiceOrderPartModel::query()->insert($parts);
            }

            if ($history !== []) {
                ServiceOrderStatusHistoryModel::query()->insert(array_map(static fn (StatusChange $change): array => [
                    'service_order_id' => $order->id->value,
                    'status' => $change->status->value,
                    'occurred_at' => self::date($change->occurredAt),
                ], $history));
            }
        });
    }

    public function findById(ServiceOrderId $id): ?ServiceOrder
    {
        $model = ServiceOrderModel::query()->with(self::RELATIONS)->find($id->value);

        return $model === null ? null : $this->toEntity($model);
    }

    public function lockById(ServiceOrderId $id): ?ServiceOrder
    {
        $model = ServiceOrderModel::query()->whereKey($id->value)->lockForUpdate()->first();

        return $model === null ? null : $this->toEntity($model->load(self::RELATIONS));
    }

    public function paginate(int $page, int $perPage, ServiceOrderFilter $filter): Page
    {
        $query = ServiceOrderModel::query()
            ->when($filter->status !== null, static fn ($q) => $q->where('status', $filter->status?->value))
            ->when($filter->customerId !== null, static fn ($q) => $q->where('customer_id', $filter->customerId))
            ->when($filter->vehicleId !== null, static fn ($q) => $q->where('vehicle_id', $filter->vehicleId));

        $total = (clone $query)->count();
        $models = $query->with(self::RELATIONS)->orderByDesc('opened_at')->orderBy('id')->forPage($page, $perPage)->get();

        return new Page(
            $models->map(fn (ServiceOrderModel $model): ServiceOrder => $this->toEntity($model))->values()->all(),
            $total,
            $page,
            $perPage,
        );
    }

    public function overdueApprovals(DateTimeImmutable $now): array
    {
        return ServiceOrderModel::query()
            ->where('status', ServiceOrderStatus::AWAITING_APPROVAL->value)
            ->where('approval_deadline', '<=', self::date($now))
            ->orderBy('approval_deadline')
            ->pluck('id')
            ->map(static fn (string $id): ServiceOrderId => ServiceOrderId::from($id))
            ->values()
            ->all();
    }

    private function toEntity(ServiceOrderModel $model): ServiceOrder
    {
        return ServiceOrder::reconstitute(
            ServiceOrderId::from($model->id),
            $model->customer_id,
            $model->vehicle_id,
            ServiceOrderStatus::from($model->status),
            $model->serviceLines->map(static fn (ServiceOrderServiceModel $line): ServiceLine => new ServiceLine(
                $line->id,
                $line->service_id,
                $line->code,
                $line->name,
                Money::fromCents($line->price_cents),
                $line->estimated_minutes,
                $line->additional,
                $line->approved,
            ))->values()->all(),
            $model->partLines->map(static fn (ServiceOrderPartModel $line): PartLine => new PartLine(
                $line->id,
                $line->inventory_item_id,
                $line->sku,
                $line->name,
                $line->unit,
                Quantity::of($line->quantity),
                Money::fromCents($line->unit_price_cents),
                $line->additional,
                $line->approved,
            ))->values()->all(),
            $model->statusHistory->map(static fn (ServiceOrderStatusHistoryModel $change): StatusChange => new StatusChange(
                ServiceOrderStatus::from($change->status),
                new DateTimeImmutable($change->occurred_at),
            ))->values()->all(),
            new DateTimeImmutable($model->opened_at),
            self::parse($model->budget_sent_at),
            self::parse($model->approval_deadline),
            self::parse($model->approved_at),
            self::parse($model->execution_started_at),
            self::parse($model->execution_finished_at),
            self::parse($model->delivered_at),
            self::parse($model->canceled_at),
            $model->cancellation_reason === null ? null : CancellationReason::from($model->cancellation_reason),
            $model->awaiting_additional_approval,
        );
    }

    private static function date(?DateTimeImmutable $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }

    private static function parse(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value);
    }
}
