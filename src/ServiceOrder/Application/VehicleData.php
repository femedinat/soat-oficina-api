<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

/** Dados do veículo informados na abertura da OS. Marca, modelo e ano só são exigidos se a placa for nova. */
final readonly class VehicleData
{
    public function __construct(
        public string $licensePlate,
        public ?string $brand = null,
        public ?string $model = null,
        public ?int $year = null,
        public ?string $color = null,
        public ?int $mileage = null,
    ) {}
}
