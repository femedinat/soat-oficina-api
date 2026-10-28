<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

final readonly class GetVehicleByLicensePlateUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function execute(string $rawLicensePlate): VehicleOutputDto
    {
        $licensePlate = LicensePlate::from($rawLicensePlate);
        $vehicle = $this->vehicleRepository->findByLicensePlate($licensePlate);

        if ($vehicle === null) {
            throw VehicleNotFoundException::withLicensePlate($licensePlate->value);
        }

        return VehicleOutputDto::fromEntity($vehicle);
    }
}
