<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Oficina\Customer\Application\DTO\CreateVehicleInputDto;
use Oficina\Customer\Application\DTO\VehicleOutputDto;
use Oficina\Customer\Application\UseCase\CreateVehicleUseCase;
use Oficina\Customer\Application\UseCase\GetVehicleByIdUseCase;
use Oficina\Customer\Application\UseCase\GetVehicleByLicensePlateUseCase;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\ServiceOrder\Application\VehicleData;
use Oficina\ServiceOrder\Application\VehicleRegistry;
use Oficina\ServiceOrder\Application\VehicleSnapshot;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Camada anticorrupção: identifica ou cadastra o veículo pelos casos de uso do contexto Veículos. */
final readonly class VehicleContextRegistry implements VehicleRegistry
{
    public function __construct(
        private GetVehicleByLicensePlateUseCase $findByPlate,
        private GetVehicleByIdUseCase $findById,
        private CreateVehicleUseCase $create,
    ) {}

    public function identifyOrRegister(string $customerId, VehicleData $data): VehicleSnapshot
    {
        try {
            $vehicle = $this->findByPlate->execute($data->licensePlate);
        } catch (VehicleNotFoundException) {
            if ($data->brand === null || $data->model === null || $data->year === null) {
                throw new InvalidValueException('Veículo não cadastrado: informe marca, modelo e ano para cadastrá-lo na abertura da OS.');
            }

            $vehicle = $this->create->execute(new CreateVehicleInputDto(
                licensePlate: $data->licensePlate,
                brand: $data->brand,
                model: $data->model,
                year: $data->year,
                customerId: $customerId,
                color: $data->color,
                mileage: $data->mileage ?? 0,
            ));
        }

        if ($vehicle->customerId !== $customerId) {
            throw new DomainException('Este veículo não pertence ao cliente informado. Transfira a propriedade no cadastro de veículos.');
        }

        if (! $vehicle->active) {
            throw new DomainException('Veículo inativo: reative o cadastro antes de abrir uma OS.');
        }

        return self::snapshot($vehicle);
    }

    public function findById(string $vehicleId): ?VehicleSnapshot
    {
        try {
            return self::snapshot($this->findById->execute($vehicleId));
        } catch (VehicleNotFoundException) {
            return null;
        }
    }

    private static function snapshot(VehicleOutputDto $vehicle): VehicleSnapshot
    {
        return new VehicleSnapshot($vehicle->id, $vehicle->customerId, $vehicle->licensePlate, $vehicle->brand, $vehicle->model, $vehicle->year, $vehicle->active);
    }
}
