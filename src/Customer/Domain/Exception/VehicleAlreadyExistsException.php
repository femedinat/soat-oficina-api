<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Exception;

use Oficina\Shared\Domain\Exception\DomainException;

final class VehicleAlreadyExistsException extends DomainException
{
    public static function withLicensePlate(string $licensePlate): self
    {
        return new self(sprintf('Já existe um veículo cadastrado com a placa %s.', $licensePlate));
    }
}
