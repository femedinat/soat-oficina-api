<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\UpdateVehicleInputDto;
use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

final readonly class UpdateVehicleUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function execute(UpdateVehicleInputDto $input): VehicleOutputDto
    {
        $vehicle = $this->vehicleRepository->findById($input->id);

        if ($vehicle === null) {
            throw VehicleNotFoundException::withId($input->id);
        }

        $newLicensePlate = null;

        if ($input->licensePlate !== null) {
            $newLicensePlate = LicensePlate::from($input->licensePlate);

            if (! $vehicle->licensePlate()->equals($newLicensePlate)
                && $this->vehicleRepository->existsByLicensePlate($newLicensePlate, $input->id)
            ) {
                throw VehicleAlreadyExistsException::withLicensePlate($newLicensePlate->value);
            }
        }

        $vehicle->update(
            brand: $input->brand,
            model: $input->model,
            year: $input->year,
            color: $input->color,
            licensePlate: $newLicensePlate,
        );

        $saved = $this->vehicleRepository->save($vehicle);

        return VehicleOutputDto::fromEntity($saved);
    }
}
