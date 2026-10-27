<?php

declare(strict_types=1);

namespace Oficina\Inventory\Interfaces\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Oficina\Inventory\Application\InventoryItemView;

/**
 * @property InventoryItemView $resource
 */
final class InventoryItemResource extends JsonResource
{
    /**
     * @return array{id: string, sku: string, name: string, description: string|null, manufacturer: string|null, type: string, unit: string, price: string, price_cents: int, physical_quantity: string, reserved_quantity: string, available_quantity: string, minimum_quantity: string, below_minimum: bool, active: bool}
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'sku' => $this->resource->sku,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'manufacturer' => $this->resource->manufacturer,
            /** @var 'PECA'|'INSUMO' */
            'type' => $this->resource->type,
            /** @var 'UN'|'L'|'KG'|'M' */
            'unit' => $this->resource->unit,
            /** Preço de venda em reais. @example 45.90 */
            'price' => $this->resource->price,
            'price_cents' => $this->resource->priceCents,
            /** Quantidades com 3 casas decimais. @example 10.000 */
            'physical_quantity' => $this->resource->physicalQuantity,
            'reserved_quantity' => $this->resource->reservedQuantity,
            'available_quantity' => $this->resource->availableQuantity,
            'minimum_quantity' => $this->resource->minimumQuantity,
            'below_minimum' => $this->resource->belowMinimum,
            'active' => $this->resource->active,
        ];
    }
}
