<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * Linha do histórico de status (append-only: o repositório só insere).
 *
 * @property int $sequence
 * @property string $service_order_id
 * @property string $status
 * @property string $occurred_at
 */
final class ServiceOrderStatusHistoryModel extends Model
{
    public $timestamps = false;

    protected $table = 'service_order_status_history';

    protected $primaryKey = 'sequence';
}
