<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Application;

use Oficina\Customer\Application\DTO\CreateVehicleInputDto;
use Oficina\Customer\Application\DTO\UpdateVehicleInputDto;
use Oficina\Customer\Application\UseCase\ActivateVehicleUseCase;
use Oficina\Customer\Application\UseCase\CreateVehicleUseCase;
use Oficina\Customer\Application\UseCase\DeactivateVehicleUseCase;
use Oficina\Customer\Application\UseCase\GetVehicleByIdUseCase;
use Oficina\Customer\Application\UseCase\GetVehicleByLicensePlateUseCase;
use Oficina\Customer\Application\UseCase\ListVehiclesUseCase;
use Oficina\Customer\Application\UseCase\RegisterVehicleMileageUseCase;
use Oficina\Customer\Application\UseCase\TransferVehicleOwnershipUseCase;
use Oficina\Customer\Application\UseCase\UpdateVehicleUseCase;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Domain\ValueObject\LicensePlate;
use PHPUnit\Framework\TestCase;

final class VehicleUseCasesTest extends TestCase
{
    private VehicleRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new class implements VehicleRepositoryInterface
        {
            /** @var array<string, Vehicle> */
            private array $items = [];

            private int $sequence = 1;

            public function save(Vehicle $vehicle): Vehicle
            {
                $id = $vehicle->id() ?? sprintf('00000000-0000-0000-0000-%012d', $this->sequence++);

                $persisted = Vehicle::create(
                    licensePlate: $vehicle->licensePlate(),
                    brand: $vehicle->brand(),
                    model: $vehicle->model(),
                    year: $vehicle->year(),
                    customerId: $vehicle->customerId(),
                    color: $vehicle->color(),
                    mileage: $vehicle->mileage(),
                    active: $vehicle->isActive(),
                    id: $id,
                );

                $this->items[$id] = $persisted;

                return $persisted;
            }

            public function findById(string $id): ?Vehicle
            {
                return $this->items[$id] ?? null;
            }

            public function findByLicensePlate(LicensePlate $licensePlate): ?Vehicle
            {
                foreach ($this->items as $vehicle) {
                    if ($vehicle->licensePlate()->equals($licensePlate)) {
                        return $vehicle;
                    }
                }

                return null;
            }

            public function findByCustomerId(string $customerId, ?bool $onlyActive = null): array
            {
                return array_values(array_filter(
                    $this->items,
                    static fn (Vehicle $vehicle): bool => $vehicle->customerId() === $customerId
                        && ($onlyActive === null || $vehicle->isActive() === $onlyActive),
                ));
            }

            public function findAll(?bool $onlyActive = null): array
            {
                return array_values(array_filter(
                    $this->items,
                    static fn (Vehicle $vehicle): bool => $onlyActive === null || $vehicle->isActive() === $onlyActive,
                ));
            }

            public function existsByLicensePlate(LicensePlate $licensePlate, ?string $excludeId = null): bool
            {
                foreach ($this->items as $vehicle) {
                    if ($excludeId !== null && $vehicle->id() === $excludeId) {
                        continue;
                    }

                    if ($vehicle->licensePlate()->equals($licensePlate)) {
                        return true;
                    }
                }

                return false;
            }
        };
    }

    public function test_creates_vehicle_and_prevents_duplicate_plate(): void
    {
        $useCase = new CreateVehicleUseCase($this->repository);

        $output = $useCase->execute(new CreateVehicleInputDto(
            licensePlate: 'bra-2e19',
            brand: 'Volkswagen',
            model: 'Virtus',
            year: 2024,
            customerId: 'customer-1',
            color: 'Cinza',
            mileage: 1200,
        ));

        self::assertSame('BRA2E19', $output->licensePlate);
        self::assertTrue($output->isMercosul);
        self::assertSame('Volkswagen', $output->brand);
        self::assertSame('Virtus', $output->model);
        self::assertSame(2024, $output->year);
        self::assertSame('customer-1', $output->customerId);
        self::assertSame('Cinza', $output->color);
        self::assertSame(1200, $output->mileage);
        self::assertTrue($output->active);
        self::assertSame($output->id, $output->toArray()['id']);

        $this->expectException(VehicleAlreadyExistsException::class);
        $useCase->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Fiat',
            model: 'Pulse',
            year: 2024,
        ));
    }

    public function test_updates_vehicle_and_checks_duplicate_plate_on_change(): void
    {
        $createUseCase = new CreateVehicleUseCase($this->repository);
        $updateUseCase = new UpdateVehicleUseCase($this->repository);

        $first = $createUseCase->execute(new CreateVehicleInputDto(
            licensePlate: 'ABC1234',
            brand: 'Fiat',
            model: 'Argo',
            year: 2021,
        ));

        $second = $createUseCase->execute(new CreateVehicleInputDto(
            licensePlate: 'DEF5678',
            brand: 'Renault',
            model: 'Kwid',
            year: 2022,
        ));

        $updated = $updateUseCase->execute(new UpdateVehicleInputDto(
            id: $first->id,
            brand: 'Fiat',
            model: 'Argo Trekking',
            year: 2022,
            color: 'Vermelho',
            licensePlate: 'GHI9J01',
        ));

        self::assertSame('Argo Trekking', $updated->model);
        self::assertSame(2022, $updated->year);
        self::assertSame('Vermelho', $updated->color);
        self::assertSame('GHI9J01', $updated->licensePlate);

        $this->expectException(VehicleAlreadyExistsException::class);
        $updateUseCase->execute(new UpdateVehicleInputDto(
            id: $first->id,
            brand: 'Fiat',
            model: 'Argo',
            year: 2022,
            licensePlate: $second->licensePlate,
        ));
    }

    public function test_update_throws_when_vehicle_not_found(): void
    {
        $updateUseCase = new UpdateVehicleUseCase($this->repository);

        $this->expectException(VehicleNotFoundException::class);
        $updateUseCase->execute(new UpdateVehicleInputDto(
            id: 'inexistente',
            brand: 'Fiat',
            model: 'Argo',
            year: 2022,
        ));
    }

    public function test_gets_vehicle_by_id_and_by_license_plate(): void
    {
        $created = (new CreateVehicleUseCase($this->repository))->execute(new CreateVehicleInputDto(
            licensePlate: 'ABC-1234',
            brand: 'Honda',
            model: 'Fit',
            year: 2018,
        ));

        $byId = (new GetVehicleByIdUseCase($this->repository))->execute($created->id);
        self::assertSame($created->id, $byId->id);

        $byPlate = (new GetVehicleByLicensePlateUseCase($this->repository))->execute('abc 1234');
        self::assertSame($created->id, $byPlate->id);
    }

    public function test_get_by_id_throws_when_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new GetVehicleByIdUseCase($this->repository))->execute('nao-existe');
    }

    public function test_get_by_plate_throws_when_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new GetVehicleByLicensePlateUseCase($this->repository))->execute('ABC1234');
    }

    public function test_lists_vehicles_with_filters(): void
    {
        $create = new CreateVehicleUseCase($this->repository);
        $deactivate = new DeactivateVehicleUseCase($this->repository);
        $list = new ListVehiclesUseCase($this->repository);

        $v1 = $create->execute(new CreateVehicleInputDto('ABC1234', 'Fiat', 'Uno', 2015, 'cust-1'));
        $v2 = $create->execute(new CreateVehicleInputDto('DEF5678', 'Ford', 'Ka', 2018, 'cust-1'));
        $create->execute(new CreateVehicleInputDto('GHI9012', 'VW', 'Gol', 2020, 'cust-2'));

        $deactivate->execute($v2->id);

        self::assertCount(3, $list->execute());
        self::assertCount(2, $list->execute(onlyActive: true));
        self::assertCount(1, $list->execute(onlyActive: false));
        self::assertSame($v2->id, $list->execute(onlyActive: false)[0]->id);
        self::assertCount(2, $list->execute(customerId: 'cust-1'));
        self::assertCount(1, $list->execute(customerId: 'cust-1', onlyActive: true));
        self::assertSame($v1->id, $list->execute(customerId: 'cust-1', onlyActive: true)[0]->id);
        self::assertCount(1, $list->execute(customerId: 'cust-1', onlyActive: false));
        self::assertSame($v2->id, $list->execute(customerId: 'cust-1', onlyActive: false)[0]->id);
    }

    public function test_registers_mileage_transfers_ownership_and_deactivates(): void
    {
        $created = (new CreateVehicleUseCase($this->repository))->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Jeep',
            model: 'Compass',
            year: 2023,
            customerId: 'cust-1',
            mileage: 5000,
        ));

        $withMileage = (new RegisterVehicleMileageUseCase($this->repository))->execute($created->id, 12500);
        self::assertSame(12500, $withMileage->mileage);

        $transferred = (new TransferVehicleOwnershipUseCase($this->repository))->execute($created->id, 'cust-2');
        self::assertSame('cust-2', $transferred->customerId);

        (new DeactivateVehicleUseCase($this->repository))->execute($created->id);
        $deactivated = (new GetVehicleByIdUseCase($this->repository))->execute($created->id);
        self::assertFalse($deactivated->active);

        $activated = (new ActivateVehicleUseCase($this->repository))->execute($created->id);
        self::assertTrue($activated->active);
    }

    public function test_register_mileage_throws_when_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new RegisterVehicleMileageUseCase($this->repository))->execute('inexistente', 1000);
    }

    public function test_transfer_ownership_throws_when_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new TransferVehicleOwnershipUseCase($this->repository))->execute('inexistente', 'cust-2');
    }

    public function test_deactivate_throws_when_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new DeactivateVehicleUseCase($this->repository))->execute('inexistente');
    }

    public function test_activate_throws_when_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new ActivateVehicleUseCase($this->repository))->execute('inexistente');
    }
}
