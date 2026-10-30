<?php

declare(strict_types=1);

namespace Oficina\Catalog\Infrastructure;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de persistência (o agregado é Oficina\Catalog\Domain\Service).
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property int $labor_price_cents
 * @property int $estimated_minutes
 * @property bool $active
 * @property Collection<int, ServiceStandardPartModel> $standardParts
 */
#[Fillable(['code', 'name', 'description', 'category', 'labor_price_cents', 'estimated_minutes', 'active'])]
final class ServiceModel extends Model
{
    public $incrementing = false;

    protected $table = 'services';

    protected $keyType = 'string';

    /** @return HasMany<ServiceStandardPartModel, $this> */
    public function standardParts(): HasMany
    {
        return $this->hasMany(ServiceStandardPartModel::class, 'service_id')->orderBy('inventory_item_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'labor_price_cents' => 'integer',
            'estimated_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }
}
