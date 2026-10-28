<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Repository;

use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\LicensePlate;

interface VehicleRepositoryInterface
{
    public function save(Vehicle $vehicle): Vehicle;

    public function findById(string $id): ?Vehicle;

    public function findByLicensePlate(LicensePlate $licensePlate): ?Vehicle;

    /**
     * @return Page<Vehicle>
     */
    public function paginate(int $page, int $perPage, ?string $customerId = null, ?bool $onlyActive = null): Page;

    public function existsByLicensePlate(LicensePlate $licensePlate, ?string $excludeId = null): bool;
}
