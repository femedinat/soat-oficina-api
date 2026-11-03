<?php

declare(strict_types=1);

namespace Oficina\Catalog\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $service_id
 * @property string $inventory_item_id
 * @property string $quantity
 */
final class ServiceStandardPartModel extends Model
{
    public $timestamps = false;

    protected $table = 'service_standard_parts';
}
