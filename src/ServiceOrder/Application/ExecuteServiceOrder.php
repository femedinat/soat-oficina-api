<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\ServiceOrder\Domain\ServiceOrder;

/**
 * Casos de uso (Fases 4 e 5): finalizar a execução (baixa automática do estoque reservado),
 * registrar a entrega do veículo e cancelar a OS (liberando a reserva, se houver).
 */
final readonly class ExecuteServiceOrder
{
    public function __construct(
        private ServiceOrderCommand $command,
        private ServiceOrderStock $stock,
    ) {}

    public function finish(string $orderId): ServiceOrderView
    {
        return $this->command->run($orderId, function (ServiceOrder $order, DateTimeImmutable $now): void {
            $order->finishServices($now);
            $this->stock->consume($order->id->value);
        });
    }

    public function deliver(string $orderId): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->deliver($now));
    }

    public function cancel(string $orderId): ServiceOrderView
    {
        return $this->command->run($orderId, function (ServiceOrder $order, DateTimeImmutable $now): void {
            $order->cancel($now);

            if ($order->hasApprovedBudget()) {
                $this->stock->release($order->id->value);
            }
        });
    }
}
