<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * Linha do extrato (append-only: o repositório só insere).
 *
 * @property int $sequence
 * @property string $id
 * @property string $inventory_item_id
 * @property string $type
 * @property string $quantity
 * @property string $physical_after
 * @property string $reserved_after
 * @property string|null $reason
 * @property string|null $reference
 * @property string $occurred_at
 */
final class StockMovementModel extends Model
{
    public $timestamps = false;

    protected $table = 'stock_movements';

    protected $primaryKey = 'sequence';
}
