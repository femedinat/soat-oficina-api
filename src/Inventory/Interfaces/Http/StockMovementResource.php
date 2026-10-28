<?php

declare(strict_types=1);

namespace Oficina\Inventory\Interfaces\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Oficina\Inventory\Application\StockMovementView;

/**
 * @property StockMovementView $resource
 */
final class StockMovementResource extends JsonResource
{
    /**
     * @return array{id: string, type: string, quantity: string, physical_after: string, reserved_after: string, reason: string|null, reference: string|null, occurred_at: string}
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            /** @var 'ESTOQUE_INICIAL'|'ENTRADA'|'AJUSTE_POSITIVO'|'AJUSTE_NEGATIVO'|'RESERVA'|'LIBERACAO_RESERVA'|'BAIXA' */
            'type' => $this->resource->type,
            'quantity' => $this->resource->quantity,
            'physical_after' => $this->resource->physicalAfter,
            'reserved_after' => $this->resource->reservedAfter,
            'reason' => $this->resource->reason,
            /** Nota fiscal (entrada) ou id da OS (reserva, liberação e baixa). */
            'reference' => $this->resource->reference,
            'occurred_at' => $this->resource->occurredAt,
        ];
    }
}
