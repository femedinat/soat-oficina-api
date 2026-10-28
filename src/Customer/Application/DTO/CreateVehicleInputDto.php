<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\DTO;

final readonly class CreateVehicleInputDto
{
    public function __construct(
        public string $licensePlate,
        public string $brand,
        public string $model,
        public int $year,
        public ?string $customerId = null,
        public ?string $color = null,
        public int $mileage = 0,
    ) {}
}
