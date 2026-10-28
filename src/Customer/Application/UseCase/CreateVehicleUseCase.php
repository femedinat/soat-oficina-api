<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\UseCase;

use Oficina\Customer\Application\DTO\CreateVehicleInputDto;
use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

final readonly class CreateVehicleUseCase
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
        private CustomerRepository $customerRepository,
    ) {}

    public function execute(CreateVehicleInputDto $input): VehicleOutputDto
    {
        $licensePlate = LicensePlate::from($input->licensePlate);

        if ($this->vehicleRepository->existsByLicensePlate($licensePlate)) {
            throw VehicleAlreadyExistsException::withLicensePlate($licensePlate->value);
        }

        $customerId = $input->customerId !== null && trim($input->customerId) !== ''
            ? trim($input->customerId)
            : null;

        if ($customerId !== null) {
            $customer = $this->customerRepository->findById(CustomerId::from($customerId))
                ?? throw new CustomerNotFoundException;

            if (! $customer->isActive()) {
                throw new DomainException('Não é possível vincular um veículo a um cliente inativo.');
            }
        }

        $vehicle = Vehicle::create(
            licensePlate: $licensePlate,
            brand: $input->brand,
            model: $input->model,
            year: $input->year,
            customerId: $customerId,
            color: $input->color,
            mileage: $input->mileage,
        );

        $saved = $this->vehicleRepository->save($vehicle);

        return VehicleOutputDto::fromEntity($saved);
    }
}
