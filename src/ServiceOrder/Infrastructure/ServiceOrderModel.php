<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de persistência (o agregado é Oficina\ServiceOrder\Domain\ServiceOrder).
 * Datas ficam como texto do banco (timestamptz) e são convertidas no repositório.
 *
 * @property string $id
 * @property string $customer_id
 * @property string $vehicle_id
 * @property string $status
 * @property string $opened_at
 * @property string|null $budget_sent_at
 * @property string|null $approval_deadline
 * @property string|null $approved_at
 * @property string|null $execution_started_at
 * @property string|null $execution_finished_at
 * @property string|null $delivered_at
 * @property string|null $canceled_at
 * @property string|null $cancellation_reason
 * @property bool $awaiting_additional_approval
 * @property Collection<int, ServiceOrderServiceModel> $serviceLines
 * @property Collection<int, ServiceOrderPartModel> $partLines
 * @property Collection<int, ServiceOrderStatusHistoryModel> $statusHistory
 */
#[Fillable(['customer_id', 'vehicle_id', 'status', 'opened_at', 'budget_sent_at', 'approval_deadline', 'approved_at', 'execution_started_at', 'execution_finished_at', 'delivered_at', 'canceled_at', 'cancellation_reason', 'awaiting_additional_approval'])]
final class ServiceOrderModel extends Model
{
    public $incrementing = false;

    protected $table = 'service_orders';

    protected $keyType = 'string';

    /** @return HasMany<ServiceOrderServiceModel, $this> */
    public function serviceLines(): HasMany
    {
        return $this->hasMany(ServiceOrderServiceModel::class, 'service_order_id')->orderBy('position');
    }

    /** @return HasMany<ServiceOrderPartModel, $this> */
    public function partLines(): HasMany
    {
        return $this->hasMany(ServiceOrderPartModel::class, 'service_order_id')->orderBy('position');
    }

    /** @return HasMany<ServiceOrderStatusHistoryModel, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistoryModel::class, 'service_order_id')->orderBy('sequence');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['awaiting_additional_approval' => 'boolean'];
    }
}
