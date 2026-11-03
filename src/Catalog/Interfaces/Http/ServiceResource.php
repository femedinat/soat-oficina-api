<?php

declare(strict_types=1);

namespace Oficina\Catalog\Interfaces\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Oficina\Catalog\Application\ServiceView;

/**
 * @property ServiceView $resource
 */
final class ServiceResource extends JsonResource
{
    /**
     * @return array{id: string, code: string, name: string, description: string|null, category: string|null, labor_price: string, labor_price_cents: int, estimated_minutes: int, standard_parts: list<array{inventory_item_id: string, quantity: string}>, active: bool}
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'category' => $this->resource->category,
            /** Preço da mão de obra em reais. @example 120.00 */
            'labor_price' => $this->resource->laborPrice,
            'labor_price_cents' => $this->resource->laborPriceCents,
            /** @example 60 */
            'estimated_minutes' => $this->resource->estimatedMinutes,
            /** Peças/insumos sugeridos quando o serviço entra numa OS. */
            'standard_parts' => $this->resource->standardParts,
            'active' => $this->resource->active,
        ];
    }
}
