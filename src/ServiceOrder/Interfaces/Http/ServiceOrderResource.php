<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Interfaces\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Oficina\ServiceOrder\Application\ServiceOrderView;

/**
 * @property ServiceOrderView $resource
 */
final class ServiceOrderResource extends JsonResource
{
    /**
     * @return array{
     *     id: string, status: string, status_label: string, customer_id: string, vehicle_id: string,
     *     services: list<array{id: string, service_id: string, code: string, name: string, price: string, price_cents: int, estimated_minutes: int, additional: bool, approved: bool}>,
     *     parts: list<array{id: string, inventory_item_id: string, sku: string, name: string, unit: string, quantity: string, unit_price: string, total: string, total_cents: int, additional: bool, approved: bool}>,
     *     budget: array{services: string, parts: string, total: string, total_cents: int},
     *     pending_approval: array{type: string, total: string, deadline: string|null}|null,
     *     opened_at: string, budget_sent_at: string|null, approved_at: string|null, execution_started_at: string|null,
     *     execution_finished_at: string|null, delivered_at: string|null, canceled_at: string|null,
     *     cancellation_reason: string|null, execution_minutes: int|null,
     *     history: list<array{status: string, label: string, occurred_at: string}>
     * }
     */
    public function toArray($request): array
    {
        $order = $this->resource;

        return [
            'id' => $order->id,
            /** @var 'RECEBIDA'|'EM_DIAGNOSTICO'|'AGUARDANDO_APROVACAO'|'EM_EXECUCAO'|'FINALIZADA'|'ENTREGUE'|'CANCELADA' */
            'status' => $order->status,
            'status_label' => $order->statusLabel,
            'customer_id' => $order->customerId,
            'vehicle_id' => $order->vehicleId,
            'services' => $order->services,
            'parts' => $order->parts,
            'budget' => $order->budget,
            /** Orçamento inicial ou reparo adicional aguardando o cliente (prazo de 48 h). */
            'pending_approval' => $order->pendingApproval,
            'opened_at' => $order->openedAt,
            'budget_sent_at' => $order->budgetSentAt,
            'approved_at' => $order->approvedAt,
            'execution_started_at' => $order->executionStartedAt,
            'execution_finished_at' => $order->executionFinishedAt,
            'delivered_at' => $order->deliveredAt,
            'canceled_at' => $order->canceledAt,
            'cancellation_reason' => $order->cancellationReason,
            /** Tempo de execução (aprovação até serviços finalizados), em minutos. */
            'execution_minutes' => $order->executionMinutes,
            'history' => $order->history,
        ];
    }
}
