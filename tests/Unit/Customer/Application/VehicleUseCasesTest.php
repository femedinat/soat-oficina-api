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
use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\Email;
use Oficina\Shared\Domain\ValueObject\LicensePlate;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCustomerRepository;

final class VehicleUseCasesTest extends TestCase
{
    private VehicleRepositoryInterface $repository;

    private InMemoryCustomerRepository $customerRepository;

    private Customer $customer1;

    private Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerRepository = new InMemoryCustomerRepository;
        $this->customer1 = Customer::register(
            'Cliente Um',
            Document::from('52998224725'),
            Email::from('um@example.com'),
        );
        $this->customer2 = Customer::register(
            'Cliente Dois',
            Document::from('11222333000181'),
            Email::from('dois@example.com'),
        );
        $this->customerRepository->save($this->customer1);
        $this->customerRepository->save($this->customer2);

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

            public function paginate(int $page, int $perPage, ?string $customerId = null, ?bool $onlyActive = null): Page
            {
                $filtered = array_values(array_filter(
                    $this->items,
                    static fn (Vehicle $vehicle): bool => ($customerId === null || $vehicle->customerId() === $customerId)
                        && ($onlyActive === null || $vehicle->isActive() === $onlyActive),
                ));

                return new Page(
                    array_slice($filtered, ($page - 1) * $perPage, $perPage),
                    count($filtered),
                    $page,
                    $perPage,
                );
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
        $useCase = new CreateVehicleUseCase($this->repository, $this->customerRepository);

        $output = $useCase->execute(new CreateVehicleInputDto(
            licensePlate: 'bra-2e19',
            brand: 'Volkswagen',
            model: 'Virtus',
            year: 2024,
            customerId: $this->customer1->id->value,
            color: 'Cinza',
            mileage: 1200,
        ));

        self::assertSame('BRA2E19', $output->licensePlate);
        self::assertTrue($output->isMercosul);
        self::assertSame('Volkswagen', $output->brand);
        self::assertSame('Virtus', $output->model);
        self::assertSame(2024, $output->year);
        self::assertSame($this->customer1->id->value, $output->customerId);
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

    public function test_create_throws_when_customer_not_found(): void
    {
        $useCase = new CreateVehicleUseCase($this->repository, $this->customerRepository);

        $this->expectException(CustomerNotFoundException::class);
        $useCase->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Volkswagen',
            model: 'Virtus',
            year: 2024,
            customerId: '00000000-0000-4000-8000-000000000099',
        ));
    }

    public function test_create_throws_when_customer_is_inactive(): void
    {
        $this->customer1->deactivate();
        $this->customerRepository->save($this->customer1);

        $useCase = new CreateVehicleUseCase($this->repository, $this->customerRepository);

        $this->expectException(DomainException::class);
        $useCase->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Volkswagen',
            model: 'Virtus',
            year: 2024,
            customerId: $this->customer1->id->value,
        ));
    }

    public function test_updates_vehicle_and_checks_duplicate_plate_on_change(): void
    {
        $createUseCase = new CreateVehicleUseCase($this->repository, $this->customerRepository);
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
        $created = (new CreateVehicleUseCase($this->repository, $this->customerRepository))->execute(new CreateVehicleInputDto(
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

    public function test_lists_vehicles_with_filters_and_pagination(): void
    {
        $create = new CreateVehicleUseCase($this->repository, $this->customerRepository);
        $deactivate = new DeactivateVehicleUseCase($this->repository);
        $list = new ListVehiclesUseCase($this->repository);

        $c1 = $this->customer1->id->value;
        $c2 = $this->customer2->id->value;

        $v1 = $create->execute(new CreateVehicleInputDto('ABC1234', 'Fiat', 'Uno', 2015, $c1));
        $v2 = $create->execute(new CreateVehicleInputDto('DEF5678', 'Ford', 'Ka', 2018, $c1));
        $create->execute(new CreateVehicleInputDto('GHI9012', 'VW', 'Gol', 2020, $c2));

        $deactivate->execute($v2->id);

        self::assertSame(3, $list->execute()->total);
        self::assertSame(2, $list->execute(onlyActive: true)->total);
        self::assertSame(1, $list->execute(onlyActive: false)->total);
        self::assertSame($v2->id, $list->execute(onlyActive: false)->items[0]->id);
        self::assertSame(2, $list->execute(customerId: $c1)->total);
        self::assertSame(1, $list->execute(customerId: $c1, onlyActive: true)->total);
        self::assertSame($v1->id, $list->execute(customerId: $c1, onlyActive: true)->items[0]->id);
        self::assertSame(1, $list->execute(customerId: $c1, onlyActive: false)->total);
        self::assertSame($v2->id, $list->execute(customerId: $c1, onlyActive: false)->items[0]->id);

        $page2 = $list->execute(page: 2, perPage: 2);
        self::assertCount(1, $page2->items);
        self::assertSame(3, $page2->total);
        self::assertSame(2, $page2->lastPage());

        $clamped = $list->execute(page: 0, perPage: 500);
        self::assertSame(1, $clamped->page);
        self::assertSame(ListVehiclesUseCase::MAX_PER_PAGE, $clamped->perPage);
    }

    public function test_registers_mileage_transfers_ownership_and_deactivates(): void
    {
        $created = (new CreateVehicleUseCase($this->repository, $this->customerRepository))->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Jeep',
            model: 'Compass',
            year: 2023,
            customerId: $this->customer1->id->value,
            mileage: 5000,
        ));

        $withMileage = (new RegisterVehicleMileageUseCase($this->repository))->execute($created->id, 12500);
        self::assertSame(12500, $withMileage->mileage);

        $transferred = (new TransferVehicleOwnershipUseCase($this->repository, $this->customerRepository))
            ->execute($created->id, $this->customer2->id->value);
        self::assertSame($this->customer2->id->value, $transferred->customerId);

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

    public function test_transfer_ownership_throws_when_vehicle_not_found(): void
    {
        $this->expectException(VehicleNotFoundException::class);
        (new TransferVehicleOwnershipUseCase($this->repository, $this->customerRepository))
            ->execute('inexistente', $this->customer2->id->value);
    }

    public function test_transfer_ownership_throws_when_customer_not_found(): void
    {
        $created = (new CreateVehicleUseCase($this->repository, $this->customerRepository))->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Jeep',
            model: 'Compass',
            year: 2023,
            customerId: $this->customer1->id->value,
        ));

        $this->expectException(CustomerNotFoundException::class);
        (new TransferVehicleOwnershipUseCase($this->repository, $this->customerRepository))
            ->execute($created->id, '00000000-0000-4000-8000-000000000099');
    }

    public function test_transfer_ownership_throws_when_customer_is_inactive(): void
    {
        $created = (new CreateVehicleUseCase($this->repository, $this->customerRepository))->execute(new CreateVehicleInputDto(
            licensePlate: 'BRA2E19',
            brand: 'Jeep',
            model: 'Compass',
            year: 2023,
            customerId: $this->customer1->id->value,
        ));

        $this->customer2->deactivate();
        $this->customerRepository->save($this->customer2);

        $this->expectException(DomainException::class);
        (new TransferVehicleOwnershipUseCase($this->repository, $this->customerRepository))
            ->execute($created->id, $this->customer2->id->value);
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
