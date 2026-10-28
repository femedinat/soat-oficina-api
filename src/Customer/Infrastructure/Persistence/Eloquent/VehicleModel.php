<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $customer_id
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property int $year
 * @property string|null $color
 * @property int $mileage
 * @property bool $active
 */
class VehicleModel extends Model
{
    use HasUuids;

    protected $table = 'vehicles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'customer_id',
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'mileage',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'active' => 'boolean',
        ];
    }
}
