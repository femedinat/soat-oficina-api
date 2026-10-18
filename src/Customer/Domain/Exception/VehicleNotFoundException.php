<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Exception;

use Oficina\Shared\Domain\Exception\DomainException;

final class VehicleNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Veículo com ID %s não encontrado.', $id));
    }

    public static function withLicensePlate(string $licensePlate): self
    {
        return new self(sprintf('Veículo com placa %s não encontrado.', $licensePlate));
    }
}
