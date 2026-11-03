<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Application\Contract\ServiceCatalog;
use Oficina\Catalog\Application\Contract\ServiceQuote;
use Oficina\Catalog\Domain\Exception\ServiceNotFoundException;
use Oficina\Catalog\Domain\ServiceId;
use Oficina\Catalog\Domain\ServiceRepository;

final readonly class CatalogServiceQuotes implements ServiceCatalog
{
    public function __construct(private ServiceRepository $services) {}

    public function quote(string $serviceId): ServiceQuote
    {
        $service = $this->services->findById(ServiceId::from($serviceId)) ?? throw new ServiceNotFoundException;
        $service->guardAvailableForBudget();
        $view = ServiceView::fromEntity($service);

        return new ServiceQuote(
            serviceId: $view->id,
            code: $view->code,
            name: $view->name,
            laborPriceCents: $view->laborPriceCents,
            estimatedMinutes: $view->estimatedMinutes,
            standardParts: $view->standardParts,
        );
    }
}
