<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $service_order_id
 * @property string $status
 */
final class StockReservationModel extends Model
{
    public $incrementing = false;

    protected $table = 'stock_reservations';

    protected $primaryKey = 'service_order_id';

    protected $keyType = 'string';

    /** @return HasMany<StockReservationLineModel, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockReservationLineModel::class, 'service_order_id');
    }
}
