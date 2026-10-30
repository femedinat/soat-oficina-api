<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Exception\ServiceNotFoundException;
use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\ServiceId;
use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;

/** Executa uma alteração em um serviço com a linha bloqueada até o commit (evita lost update). */
final readonly class LockedServiceCommand
{
    public function __construct(
        private ServiceRepository $services,
        private TransactionManager $transaction,
        private EventPublisher $events,
    ) {}

    /** @param callable(Service): void $change */
    public function run(string $id, callable $change): ServiceView
    {
        $serviceId = ServiceId::from($id);

        return $this->transaction->run(function () use ($serviceId, $change): ServiceView {
            $service = $this->services->lockById($serviceId) ?? throw new ServiceNotFoundException;

            $change($service);

            $this->services->save($service);
            $this->events->publish(...$service->pullEvents());

            return ServiceView::fromEntity($service);
        });
    }
}
