<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

/** Projeção pública da OS para o cliente acompanhar sem login. */
final readonly class TrackingView
{
    /**
     * @param  array{type: string, total: string, deadline: string|null}|null  $pendingApproval
     * @param  list<array{status: string, label: string, occurred_at: string}>  $history
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $statusLabel,
        public ?string $licensePlate,
        public ?string $vehicle,
        public string $total,
        public ?array $pendingApproval,
        public array $history,
    ) {}

    public static function of(ServiceOrderView $order, ?VehicleSnapshot $vehicle): self
    {
        return new self(
            id: $order->id,
            status: $order->status,
            statusLabel: $order->statusLabel,
            licensePlate: $vehicle?->licensePlate,
            vehicle: $vehicle === null ? null : trim(sprintf('%s %s %d', $vehicle->brand, $vehicle->model, $vehicle->year)),
            total: $order->budget['total'],
            pendingApproval: $order->pendingApproval,
            history: $order->history,
        );
    }
}
