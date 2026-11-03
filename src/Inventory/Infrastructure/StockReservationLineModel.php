<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $service_order_id
 * @property string $inventory_item_id
 * @property string $quantity
 */
final class StockReservationLineModel extends Model
{
    public $timestamps = false;

    protected $table = 'stock_reservation_lines';
}
