<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;

final readonly class DeactivateVehicleUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function execute(string $id): void
    {
        $vehicle = $this->vehicleRepository->findById($id);

        if ($vehicle === null) {
            throw VehicleNotFoundException::withId($id);
        }

        $vehicle->deactivate();

        $this->vehicleRepository->save($vehicle);
    }
}
