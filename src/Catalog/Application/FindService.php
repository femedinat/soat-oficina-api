<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Exception\ServiceNotFoundException;
use Oficina\Catalog\Domain\ServiceId;
use Oficina\Catalog\Domain\ServiceRepository;

/** Caso de uso: consultar a ficha do serviço. */
final readonly class FindService
{
    public function __construct(private ServiceRepository $services) {}

    public function execute(string $id): ServiceView
    {
        $service = $this->services->findById(ServiceId::from($id)) ?? throw new ServiceNotFoundException;

        return ServiceView::fromEntity($service);
    }
}
