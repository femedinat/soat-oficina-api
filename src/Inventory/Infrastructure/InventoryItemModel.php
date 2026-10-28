<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de persistência (detalhe de infraestrutura; o agregado é Oficina\Inventory\Domain\InventoryItem).
 *
 * @property string $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string|null $manufacturer
 * @property string $type
 * @property string $unit
 * @property int $price_cents
 * @property string $physical_quantity
 * @property string $reserved_quantity
 * @property string $minimum_quantity
 * @property bool $active
 */
#[Fillable(['sku', 'name', 'description', 'manufacturer', 'type', 'unit', 'price_cents', 'physical_quantity', 'reserved_quantity', 'minimum_quantity', 'active'])]
final class InventoryItemModel extends Model
{
    public $incrementing = false;

    protected $table = 'inventory_items';

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'active' => 'boolean',
        ];
    }
}
