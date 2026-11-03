<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\ServiceOrder\Domain\ServiceOrderFilter;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;
use Oficina\ServiceOrder\Domain\ServiceOrderStatus;
use Oficina\Shared\Application\Page;

/** Caso de uso: listar OS (mais recentes primeiro). O escopo do cliente sobrepõe qualquer filtro de cliente. */
final readonly class ListServiceOrders
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(private ServiceOrderRepository $orders) {}

    /** @return Page<ServiceOrderView> */
    public function execute(
        int $page = 1,
        int $perPage = 15,
        ?string $status = null,
        ?string $customerId = null,
        ?string $vehicleId = null,
        ?string $customerScope = null,
    ): Page {
        $filter = new ServiceOrderFilter(
            status: $status === null ? null : ServiceOrderStatus::parse($status),
            customerId: $customerScope ?? $customerId,
            vehicleId: $vehicleId,
        );

        $result = $this->orders->paginate(max(1, $page), min(max(1, $perPage), self::MAX_PER_PAGE), $filter);

        return new Page(
            array_map(static fn (ServiceOrder $order): ServiceOrderView => ServiceOrderView::fromEntity($order), $result->items),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }
}
