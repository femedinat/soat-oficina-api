<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;

final readonly class ListVehiclesUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    /**
     * @return list<VehicleOutputDto>
     */
    public function execute(?string $customerId = null, ?bool $onlyActive = null): array
    {
        $vehicles = $customerId !== null && trim($customerId) !== ''
            ? $this->vehicleRepository->findByCustomerId(trim($customerId), $onlyActive)
            : $this->vehicleRepository->findAll($onlyActive);

        return array_map(
            static fn (Vehicle $vehicle): VehicleOutputDto => VehicleOutputDto::fromEntity($vehicle),
            $vehicles,
        );
    }
}
