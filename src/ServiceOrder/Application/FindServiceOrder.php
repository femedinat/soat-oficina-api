<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\ServiceOrder\Domain\Exception\ServiceOrderNotFoundException;
use Oficina\ServiceOrder\Domain\ServiceOrderId;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;

/** Caso de uso: consultar uma OS. Para o cliente, só a dele (outra OS = 404, sem vazar existência). */
final readonly class FindServiceOrder
{
    public function __construct(private ServiceOrderRepository $orders) {}

    public function execute(string $id, ?string $customerScope = null): ServiceOrderView
    {
        $order = $this->orders->findById(ServiceOrderId::from($id)) ?? throw new ServiceOrderNotFoundException;

        if ($customerScope !== null && $order->customerId !== $customerScope) {
            throw new ServiceOrderNotFoundException;
        }

        return ServiceOrderView::fromEntity($order);
    }
}
