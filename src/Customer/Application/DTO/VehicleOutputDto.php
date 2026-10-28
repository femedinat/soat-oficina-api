<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\DTO;

use Oficina\Customer\Domain\Entity\Vehicle;

final readonly class VehicleOutputDto
{
    public function __construct(
        public string $id,
        public ?string $customerId,
        public string $licensePlate,
        public bool $isMercosul,
        public string $brand,
        public string $model,
        public int $year,
        public ?string $color,
        public int $mileage,
        public bool $active,
    ) {}

    public static function fromEntity(Vehicle $vehicle): self
    {
        return new self(
            id: (string) $vehicle->id(),
            customerId: $vehicle->customerId(),
            licensePlate: $vehicle->licensePlate()->value,
            isMercosul: $vehicle->licensePlate()->isMercosul(),
            brand: $vehicle->brand(),
            model: $vehicle->model(),
            year: $vehicle->year(),
            color: $vehicle->color(),
            mileage: $vehicle->mileage(),
            active: $vehicle->isActive(),
        );
    }

    /**
     * @return array{
     *     id: string,
     *     customer_id: ?string,
     *     license_plate: string,
     *     is_mercosul: bool,
     *     brand: string,
     *     model: string,
     *     year: int,
     *     color: ?string,
     *     mileage: int,
     *     active: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'license_plate' => $this->licensePlate,
            'is_mercosul' => $this->isMercosul,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'mileage' => $this->mileage,
            'active' => $this->active,
        ];
    }
}
