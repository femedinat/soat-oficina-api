<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\Exception\ServiceOrderNotFoundException;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\ServiceOrderId;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;
use Oficina\Shared\Application\Clock;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;

/**
 * Roteiro comum dos comandos da OS: transação -> OS bloqueada (SELECT ... FOR UPDATE) -> regra -> grava -> eventos.
 * O bloqueio evita duas ações concorrentes (ex.: cliente aprova enquanto o job de 48 h cancela).
 * Estoque (reserva/baixa/liberação) roda dentro da mesma transação: tudo ou nada.
 */
final readonly class ServiceOrderCommand
{
    public function __construct(
        private ServiceOrderRepository $orders,
        private TransactionManager $transaction,
        private EventPublisher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  callable(ServiceOrder, DateTimeImmutable): void  $change
     * @param  string|null  $customerScope  quando o ator é um cliente: só a OS dele (outra OS = 404, sem vazar existência)
     */
    public function run(string $id, callable $change, ?string $customerScope = null): ServiceOrderView
    {
        $orderId = ServiceOrderId::from($id);

        return $this->transaction->run(function () use ($orderId, $change, $customerScope): ServiceOrderView {
            $order = $this->orders->lockById($orderId) ?? throw new ServiceOrderNotFoundException;

            if ($customerScope !== null && $order->customerId !== $customerScope) {
                throw new ServiceOrderNotFoundException;
            }

            $change($order, $this->clock->now());

            $this->orders->save($order);
            $this->events->publish(...$order->pullEvents());

            return ServiceOrderView::fromEntity($order);
        });
    }
}
