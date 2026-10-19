<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Repository;

use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): Vehicle;

    public function findById(string $id): ?Vehicle;

    public function findByLicensePlate(LicensePlate $licensePlate): ?Vehicle;

    /**
     * @return list<Vehicle>
     */
    public function findByCustomerId(string $customerId, ?bool $onlyActive = null): array;

    /**
     * @return list<Vehicle>
     */
    public function findAll(?bool $onlyActive = null): array;

    public function existsByLicensePlate(LicensePlate $licensePlate, ?string $excludeId = null): bool;
}
