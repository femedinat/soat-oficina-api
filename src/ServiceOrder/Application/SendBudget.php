<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\ServiceOrder;

/** Caso de uso (Fase 2): calcular o orçamento e enviá-lo ao cliente para aprovação (e-mail após o commit). */
final readonly class SendBudget
{
    public function __construct(private ServiceOrderCommand $command) {}

    public function execute(string $orderId): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->sendBudget($now));
    }

    public function returnToDiagnosis(string $orderId): ServiceOrderView
    {
        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->returnToDiagnosis($now));
    }
}
