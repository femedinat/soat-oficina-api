<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

/** O que a OS sabe de um veículo (cópia de leitura vinda do contexto Clientes e Veículos). */
final readonly class VehicleSnapshot
{
    public function __construct(
        public string $id,
        public ?string $customerId,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public int $year,
        public bool $active,
    ) {}
}
