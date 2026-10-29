<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * Serviço orçado na OS (cópia congelada do catálogo).
 *
 * @property string $id
 * @property string $service_order_id
 * @property string $service_id
 * @property int $position
 * @property string $code
 * @property string $name
 * @property int $price_cents
 * @property int $estimated_minutes
 * @property bool $additional
 * @property bool $approved
 */
final class ServiceOrderServiceModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'service_order_services';

    protected $keyType = 'string';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'price_cents' => 'integer',
            'estimated_minutes' => 'integer',
            'additional' => 'boolean',
            'approved' => 'boolean',
        ];
    }
}
