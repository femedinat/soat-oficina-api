<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * Peça/insumo orçado na OS (preço unitário congelado).
 *
 * @property string $id
 * @property string $service_order_id
 * @property string $inventory_item_id
 * @property int $position
 * @property string $sku
 * @property string $name
 * @property string $unit
 * @property string $quantity
 * @property int $unit_price_cents
 * @property bool $additional
 * @property bool $approved
 */
final class ServiceOrderPartModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'service_order_parts';

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'unit_price_cents' => 'integer',
            'additional' => 'boolean',
            'approved' => 'boolean',
        ];
    }
}
