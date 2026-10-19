<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\CreateVehicleInputDto;
use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

final readonly class CreateVehicleUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function execute(CreateVehicleInputDto $input): VehicleOutputDto
    {
        $licensePlate = LicensePlate::from($input->licensePlate);

        if ($this->vehicleRepository->existsByLicensePlate($licensePlate)) {
            throw VehicleAlreadyExistsException::withLicensePlate($licensePlate->value);
        }

        $vehicle = Vehicle::create(
            licensePlate: $licensePlate,
            brand: $input->brand,
            model: $input->model,
            year: $input->year,
            customerId: $input->customerId,
            color: $input->color,
            mileage: $input->mileage,
        );

        $saved = $this->vehicleRepository->save($vehicle);

        return VehicleOutputDto::fromEntity($saved);
    }
}
