<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Caso de uso (monitoramento): tempo médio de execução das OS (aprovação -> serviços finalizados),
 * geral e por serviço, comparado ao tempo estimado do catálogo congelado na OS.
 * Por serviço, a média é a das OS que contêm o serviço (a OS não cronometra cada serviço isoladamente).
 */
final readonly class GetExecutionTimeReport
{
    public function __construct(private ExecutionTimeStatistics $statistics) {}

    /**
     * @return array{
     *     period: array{from: string|null, to: string|null},
     *     overall: array{orders: int, average_minutes: int|null, min_minutes: int|null, max_minutes: int|null, average_estimated_minutes: int|null},
     *     services: list<array{service_id: string, code: string, name: string, orders: int, average_minutes: int, estimated_minutes: int, deviation_minutes: int}>
     * }
     */
    public function execute(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): array
    {
        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidValueException('O início do período deve ser anterior ao fim.');
        }

        $overall = $this->statistics->overall($from, $to);

        return [
            'period' => [
                'from' => $from?->format(DateTimeInterface::ATOM),
                'to' => $to?->format(DateTimeInterface::ATOM),
            ],
            'overall' => [
                'orders' => $overall['orders'],
                'average_minutes' => self::minutes($overall['average_seconds']),
                'min_minutes' => self::minutes($overall['min_seconds']),
                'max_minutes' => self::minutes($overall['max_seconds']),
                'average_estimated_minutes' => $overall['average_estimated_minutes'],
            ],
            'services' => array_map(static function (array $row): array {
                $average = (int) self::minutes($row['average_seconds']);

                return [
                    'service_id' => $row['service_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'orders' => $row['orders'],
                    'average_minutes' => $average,
                    'estimated_minutes' => $row['estimated_minutes'],
                    'deviation_minutes' => $average - $row['estimated_minutes'],
                ];
            }, $this->statistics->perService($from, $to)),
        ];
    }

    private static function minutes(?int $seconds): ?int
    {
        return $seconds === null ? null : intdiv($seconds + 59, 60);
    }
}
