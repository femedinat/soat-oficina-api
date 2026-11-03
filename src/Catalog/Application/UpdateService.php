<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Exception\ServiceAlreadyRegisteredException;
use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\ServiceRepository;

/** Caso de uso: atualizar dados do serviço (nome, descrição, categoria). */
final readonly class UpdateService
{
    public function __construct(
        private LockedServiceCommand $command,
        private ServiceRepository $services,
    ) {}

    public function execute(string $id, string $name, ?string $description, ?string $category): ServiceView
    {
        return $this->command->run($id, function (Service $service) use ($name, $description, $category): void {
            $service->revise($name, $description, $category);

            if ($this->services->nameExists($service->name(), $service->id)) {
                throw ServiceAlreadyRegisteredException::withName($service->name());
            }
        });
    }
}
