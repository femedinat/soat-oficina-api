<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\Exception\DomainException;

final readonly class TransferVehicleOwnershipUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
        private CustomerRepository $customerRepository,
    ) {}

    public function execute(string $id, string $newCustomerId): VehicleOutputDto
    {
        $vehicle = $this->vehicleRepository->findById($id);

        if ($vehicle === null) {
            throw VehicleNotFoundException::withId($id);
        }

        $vehicle->transferOwnership($newCustomerId);

        $customer = $this->customerRepository->findById(CustomerId::from((string) $vehicle->customerId()))
            ?? throw new CustomerNotFoundException;

        if (! $customer->isActive()) {
            throw new DomainException('Não é possível transferir um veículo para um cliente inativo.');
        }

        $saved = $this->vehicleRepository->save($vehicle);

        return VehicleOutputDto::fromEntity($saved);
    }
}
