<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use DateTimeImmutable;
use Oficina\Shared\Application\Page;

interface ServiceOrderRepository
{
    public function save(ServiceOrder $order): void;

    public function findById(ServiceOrderId $id): ?ServiceOrder;

    /** SELECT ... FOR UPDATE: use dentro de uma transação. */
    public function lockById(ServiceOrderId $id): ?ServiceOrder;

    /** @return Page<ServiceOrder> mais recentes primeiro */
    public function paginate(int $page, int $perPage, ServiceOrderFilter $filter): Page;

    /** @return list<ServiceOrderId> OS aguardando aprovação com o prazo vencido em $now */
    public function overdueApprovals(DateTimeImmutable $now): array;
}
