<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;

/** Política: quando o Estoque inativa uma peça/insumo, ela deixa de ser padrão em todos os serviços. */
final readonly class RemoveDeactivatedPartFromServices
{
    public function __construct(
        private ServiceRepository $services,
        private TransactionManager $transaction,
        private EventPublisher $events,
    ) {}

    /** @return int quantidade de serviços alterados */
    public function execute(string $inventoryItemId): int
    {
        return $this->transaction->run(function () use ($inventoryItemId): int {
            $services = $this->services->lockWithStandardPart($inventoryItemId);

            foreach ($services as $service) {
                $service->unlinkStandardPart($inventoryItemId);
                $this->services->save($service);
                $this->events->publish(...$service->pullEvents());
            }

            return count($services);
        });
    }
}
