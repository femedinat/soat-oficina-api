<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

final readonly class ServiceOrderFilter
{
    public function __construct(
        public ?ServiceOrderStatus $status = null,
        public ?string $customerId = null,
        public ?string $vehicleId = null,
    ) {}
}
