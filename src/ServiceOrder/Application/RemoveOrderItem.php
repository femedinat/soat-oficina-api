<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\ServiceOrder;

/** Caso de uso (Fase 2): remover serviço ou peça lançado por engano durante o diagnóstico. */
final readonly class RemoveOrderItem
{
    public function __construct(private ServiceOrderCommand $command) {}

    public function service(string $orderId, string $lineId): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->removeService(strtolower($lineId), $now));
    }

    public function part(string $orderId, string $lineId): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->removePart(strtolower($lineId), $now));
    }
}
