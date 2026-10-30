<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Oficina\ServiceOrder\Domain\Budget;
use Oficina\ServiceOrder\Domain\PartLine;
use Oficina\ServiceOrder\Domain\ServiceLine;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\StatusChange;

/**
 * Projeção de leitura da OS para a camada de Interfaces.
 *
 * @phpstan-type BudgetArray array{services: string, parts: string, total: string, total_cents: int}
 * @phpstan-type ServiceLineArray array{id: string, service_id: string, code: string, name: string, price: string, price_cents: int, estimated_minutes: int, additional: bool, approved: bool}
 * @phpstan-type PartLineArray array{id: string, inventory_item_id: string, sku: string, name: string, unit: string, quantity: string, unit_price: string, total: string, total_cents: int, additional: bool, approved: bool}
 * @phpstan-type HistoryArray array{status: string, label: string, occurred_at: string}
 */
final readonly class ServiceOrderView
{
    /**
     * @param  list<ServiceLineArray>  $services
     * @param  list<PartLineArray>  $parts
     * @param  BudgetArray  $budget
     * @param  array{type: string, total: string, deadline: string|null}|null  $pendingApproval
     * @param  list<HistoryArray>  $history
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $statusLabel,
        public string $customerId,
        public string $vehicleId,
        public array $services,
        public array $parts,
        public array $budget,
        public ?array $pendingApproval,
        public string $openedAt,
        public ?string $budgetSentAt,
        public ?string $approvedAt,
        public ?string $executionStartedAt,
        public ?string $executionFinishedAt,
        public ?string $deliveredAt,
        public ?string $canceledAt,
        public ?string $cancellationReason,
        public ?int $executionMinutes,
        public array $history,
    ) {}

    public static function fromEntity(ServiceOrder $order): self
    {
        $pending = null;
        if ($order->approvalDeadline() !== null) {
            $pending = [
                'type' => $order->isAwaitingAdditionalApproval() ? 'REPARO_ADICIONAL' : 'ORCAMENTO_INICIAL',
                'total' => $order->pendingBudget()->total()->toDecimal(),
                'deadline' => self::date($order->approvalDeadline()),
            ];
        }

        $seconds = $order->executionSeconds();

        return new self(
            id: $order->id->value,
            status: $order->status()->value,
            statusLabel: $order->status()->label(),
            customerId: $order->customerId,
            vehicleId: $order->vehicleId,
            services: array_map(static fn (ServiceLine $line): array => [
                'id' => $line->id,
                'service_id' => $line->serviceId,
                'code' => $line->code,
                'name' => $line->name,
                'price' => $line->price->toDecimal(),
                'price_cents' => $line->price->cents,
                'estimated_minutes' => $line->estimatedMinutes,
                'additional' => $line->additional,
                'approved' => $line->approved,
            ], $order->services()),
            parts: array_map(static fn (PartLine $line): array => [
                'id' => $line->id,
                'inventory_item_id' => $line->inventoryItemId,
                'sku' => $line->sku,
                'name' => $line->name,
                'unit' => $line->unit,
                'quantity' => $line->quantity->toDecimal(),
                'unit_price' => $line->unitPrice->toDecimal(),
                'total' => $line->total()->toDecimal(),
                'total_cents' => $line->total()->cents,
                'additional' => $line->additional,
                'approved' => $line->approved,
            ], $order->parts()),
            budget: self::budget($order->budget()),
            pendingApproval: $pending,
            openedAt: (string) self::date($order->openedAt()),
            budgetSentAt: self::date($order->budgetSentAt()),
            approvedAt: self::date($order->approvedAt()),
            executionStartedAt: self::date($order->executionStartedAt()),
            executionFinishedAt: self::date($order->executionFinishedAt()),
            deliveredAt: self::date($order->deliveredAt()),
            canceledAt: self::date($order->canceledAt()),
            cancellationReason: $order->cancellationReason()?->label(),
            executionMinutes: $seconds === null ? null : intdiv($seconds + 59, 60),
            history: array_map(static fn (StatusChange $change): array => [
                'status' => $change->status->value,
                'label' => $change->status->label(),
                'occurred_at' => (string) self::date($change->occurredAt),
            ], $order->history()),
        );
    }

    /** @return BudgetArray */
    private static function budget(Budget $budget): array
    {
        return [
            'services' => $budget->services->toDecimal(),
            'parts' => $budget->parts->toDecimal(),
            'total' => $budget->total()->toDecimal(),
            'total_cents' => $budget->total()->cents,
        ];
    }

    private static function date(?DateTimeImmutable $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }
}
