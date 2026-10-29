<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\Inventory\Application\Contract\StockRequest;
use Oficina\ServiceOrder\Domain\PartLine;
use Oficina\ServiceOrder\Domain\ServiceOrder;

/**
 * Casos de uso (Fases 3 e 4): o cliente aprova ou rejeita o orçamento inicial ou o reparo adicional.
 * Aprovar reserva as peças pendentes na MESMA transação (tudo ou nada): sem estoque, nada muda e a OS
 * continua aguardando aprovação (o mecânico pode devolver ao diagnóstico e ajustar).
 */
final readonly class DecideBudget
{
    public function __construct(
        private ServiceOrderCommand $command,
        private ServiceOrderStock $stock,
    ) {}

    public function approve(string $orderId, ?string $customerScope = null): ServiceOrderView
    {
        return $this->command->run($orderId, function (ServiceOrder $order, DateTimeImmutable $now): void {
            $toReserve = $order->pendingParts();
            $order->approveBudget($now);
            $this->reserve($order, $toReserve);
        }, $customerScope);
    }

    public function reject(string $orderId, ?string $customerScope = null): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->rejectBudget($now), $customerScope);
    }

    public function authorizeAdditional(string $orderId, ?string $customerScope = null): ServiceOrderView
    {
        return $this->command->run($orderId, function (ServiceOrder $order, DateTimeImmutable $now): void {
            $toReserve = $order->pendingParts();
            $order->authorizeAdditionalRepair($now);
            $this->reserve($order, $toReserve);
        }, $customerScope);
    }

    public function refuseAdditional(string $orderId, ?string $customerScope = null): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->refuseAdditionalRepair($now), $customerScope);
    }

    /** @param list<PartLine> $parts */
    private function reserve(ServiceOrder $order, array $parts): void
    {
        if ($parts === []) {
            return;
        }

        $this->stock->reserve($order->id->value, array_map(
            static fn (PartLine $line): StockRequest => new StockRequest($line->inventoryItemId, $line->quantity->toDecimal()),
            $parts,
        ));
    }
}
