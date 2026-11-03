<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;

final readonly class RegisterVehicleMileageUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function execute(string $id, int $mileage): VehicleOutputDto
    {
        $vehicle = $this->vehicleRepository->findById($id);

        if ($vehicle === null) {
            throw VehicleNotFoundException::withId($id);
        }

        $vehicle->registerMileage($mileage);

        $saved = $this->vehicleRepository->save($vehicle);

        return VehicleOutputDto::fromEntity($saved);
    }
}
