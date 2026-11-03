<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;

final readonly class GetVehicleByIdUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function execute(string $id): VehicleOutputDto
    {
        $vehicle = $this->vehicleRepository->findById($id);

        if ($vehicle === null) {
            throw VehicleNotFoundException::withId($id);
        }

        return VehicleOutputDto::fromEntity($vehicle);
    }
}
