<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;
use Oficina\ServiceOrder\Domain\ServiceOrderStatus;
use Oficina\Shared\Application\Clock;

/**
 * Política "Se 48 h sem resposta" (job agendado): cada OS vencida é tratada na sua própria transação,
 * com a OS bloqueada. Se o cliente decidiu entre a consulta e o bloqueio, a OS é ignorada.
 */
final readonly class ExpireOverdueApprovals
{
    public function __construct(
        private ServiceOrderRepository $orders,
        private ServiceOrderCommand $command,
        private Clock $clock,
    ) {}

    /** @return int quantidade de OS tratadas */
    public function execute(): int
    {
        $expired = 0;

        foreach ($this->orders->overdueApprovals($this->clock->now()) as $id) {
            $this->command->run($id->value, static function (ServiceOrder $order, DateTimeImmutable $now) use (&$expired): void {
                if ($order->status() !== ServiceOrderStatus::AWAITING_APPROVAL || ! $order->isApprovalOverdue($now)) {
                    return;
                }

                $order->expireApproval($now);
                $expired++;
            });
        }

        return $expired;
    }
}
