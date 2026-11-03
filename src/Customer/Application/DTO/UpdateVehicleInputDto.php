<?php

declare(strict_types=1);

namespace Oficina\Customer\Application\DTO;

final readonly class UpdateVehicleInputDto
{
    public function __construct(
        public string $id,
        public string $brand,
        public string $model,
        public int $year,
        public ?string $color = null,
        public ?string $licensePlate = null,
    ) {}
}
