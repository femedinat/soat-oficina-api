<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Oficina\ServiceOrder\Application\ExecutionTimeStatistics;
use Oficina\ServiceOrder\Domain\ServiceOrderStatus;

/**
 * Read model calculado sob demanda direto no banco (agregação SQL, sem carregar agregados).
 * Fonte: execution_started_at (aprovação) -> execution_finished_at (serviços finalizados).
 */
final class QueryExecutionTimeStatistics implements ExecutionTimeStatistics
{
    private const string DURATION = 'EXTRACT(EPOCH FROM (so.execution_finished_at - so.execution_started_at))';

    public function overall(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        // Tempo estimado da OS = soma dos serviços aprovados (cópia congelada do catálogo).
        $estimated = DB::table('service_order_services')
            ->select('service_order_id')
            ->selectRaw('SUM(estimated_minutes) AS minutes')
            ->where('approved', true)
            ->groupBy('service_order_id');

        $row = $this->finished($from, $to)
            ->leftJoinSub($estimated, 'est', 'est.service_order_id', '=', 'so.id')
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw('ROUND(AVG('.self::DURATION.')) AS average_seconds')
            ->selectRaw('ROUND(MIN('.self::DURATION.')) AS min_seconds')
            ->selectRaw('ROUND(MAX('.self::DURATION.')) AS max_seconds')
            ->selectRaw('ROUND(AVG(est.minutes)) AS average_estimated_minutes')
            ->first();

        return [
            'orders' => (int) ($row->orders ?? 0),
            'average_seconds' => self::int($row->average_seconds ?? null),
            'min_seconds' => self::int($row->min_seconds ?? null),
            'max_seconds' => self::int($row->max_seconds ?? null),
            'average_estimated_minutes' => self::int($row->average_estimated_minutes ?? null),
        ];
    }

    public function perService(?DateTimeImmutable $from, ?DateTimeImmutable $to): array
    {
        return $this->finished($from, $to)
            ->join('service_order_services as sos', 'sos.service_order_id', '=', 'so.id')
            ->join('services as s', 's.id', '=', 'sos.service_id')
            ->where('sos.approved', true)
            ->groupBy('s.id', 's.code', 's.name')
            ->select('s.id as service_id', 's.code', 's.name')
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw('ROUND(AVG('.self::DURATION.')) AS average_seconds')
            ->selectRaw('ROUND(AVG(sos.estimated_minutes)) AS estimated_minutes')
            ->orderBy('s.name')
            ->get()
            ->map(static fn (object $row): array => [
                'service_id' => (string) $row->service_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'orders' => (int) $row->orders,
                'average_seconds' => (int) $row->average_seconds,
                'estimated_minutes' => (int) $row->estimated_minutes,
            ])
            ->values()
            ->all();
    }

    private function finished(?DateTimeImmutable $from, ?DateTimeImmutable $to): Builder
    {
        return DB::table('service_orders as so')
            ->whereIn('so.status', [ServiceOrderStatus::COMPLETED->value, ServiceOrderStatus::DELIVERED->value])
            ->whereNotNull('so.execution_started_at')
            ->whereNotNull('so.execution_finished_at')
            ->when($from !== null, static fn (Builder $q) => $q->where('so.execution_finished_at', '>=', $from?->format('Y-m-d H:i:s.uP')))
            ->when($to !== null, static fn (Builder $q) => $q->where('so.execution_finished_at', '<=', $to?->format('Y-m-d H:i:s.uP')));
    }

    private static function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
