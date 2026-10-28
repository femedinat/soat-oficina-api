<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Domain;

use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Domain\Exception\VehicleNotFoundException;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\LicensePlate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VehicleTest extends TestCase
{
    public function test_creates_valid_vehicle(): void
    {
        $plate = LicensePlate::from('bra-2e19');

        $vehicle = Vehicle::create(
            licensePlate: $plate,
            brand: '  Fiat  ',
            model: '  Uno Mille  ',
            year: 2012,
            customerId: '  550e8400-e29b-41d4-a716-446655440000  ',
            color: '  Prata  ',
            mileage: 85000,
            active: true,
            id: '123e4567-e89b-12d3-a456-426614174000',
        );

        self::assertSame('123e4567-e89b-12d3-a456-426614174000', $vehicle->id());
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $vehicle->customerId());
        self::assertSame('BRA2E19', $vehicle->licensePlate()->value);
        self::assertTrue($vehicle->licensePlate()->isMercosul());
        self::assertSame('Fiat', $vehicle->brand());
        self::assertSame('Uno Mille', $vehicle->model());
        self::assertSame(2012, $vehicle->year());
        self::assertSame('Prata', $vehicle->color());
        self::assertSame(85000, $vehicle->mileage());
        self::assertTrue($vehicle->isActive());
    }

    public function test_normalizes_empty_optional_fields_to_null(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Volkswagen',
            model: 'Gol',
            year: 2020,
            customerId: '   ',
            color: '',
        );

        self::assertNull($vehicle->id());
        self::assertNull($vehicle->customerId());
        self::assertNull($vehicle->color());
        self::assertSame(0, $vehicle->mileage());
        self::assertTrue($vehicle->isActive());
    }

    #[DataProvider('invalidCreationData')]
    public function test_rejects_invalid_creation_data(
        string $brand,
        string $model,
        int $year,
        int $mileage,
    ): void {
        $this->expectException(InvalidValueException::class);

        Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: $brand,
            model: $model,
            year: $year,
            mileage: $mileage,
        );
    }

    /**
     * @return array<string, array{string, string, int, int}>
     */
    public static function invalidCreationData(): array
    {
        $futureInvalidYear = (int) date('Y') + 2;

        return [
            'marca vazia' => ['', 'Civic', 2022, 0],
            'marca só espaços' => ['   ', 'Civic', 2022, 0],
            'modelo vazio' => ['Honda', '', 2022, 0],
            'modelo só espaços' => ['Honda', '   ', 2022, 0],
            'ano muito antigo' => ['Honda', 'Civic', 1899, 0],
            'ano no futuro inválido' => ['Honda', 'Civic', $futureInvalidYear, 0],
            'quilometragem negativa' => ['Honda', 'Civic', 2022, -1],
        ];
    }

    public function test_updates_vehicle_data_and_license_plate(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Chevrolet',
            model: 'Onix',
            year: 2019,
            color: 'Branco',
        );

        $vehicle->update(
            brand: 'Chevrolet',
            model: 'Onix Plus',
            year: 2021,
            color: 'Preto',
            licensePlate: LicensePlate::from('BRA2E19'),
        );

        self::assertSame('Chevrolet', $vehicle->brand());
        self::assertSame('Onix Plus', $vehicle->model());
        self::assertSame(2021, $vehicle->year());
        self::assertSame('Preto', $vehicle->color());
        self::assertSame('BRA2E19', $vehicle->licensePlate()->value);
    }

    public function test_rejects_update_on_inactive_vehicle(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Chevrolet',
            model: 'Onix',
            year: 2019,
        );
        $vehicle->deactivate();

        $this->expectException(DomainException::class);
        $vehicle->update(
            brand: 'Chevrolet',
            model: 'Onix Plus',
            year: 2021,
        );
    }

    public function test_registers_mileage_evolution(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Toyota',
            model: 'Corolla',
            year: 2021,
            mileage: 10000,
        );

        $vehicle->registerMileage(15000);

        self::assertSame(15000, $vehicle->mileage());
    }

    public function test_rejects_lower_mileage(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Toyota',
            model: 'Corolla',
            year: 2021,
            mileage: 10000,
        );

        $this->expectException(InvalidValueException::class);
        $vehicle->registerMileage(9999);
    }

    public function test_rejects_mileage_on_inactive_vehicle(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Toyota',
            model: 'Corolla',
            year: 2021,
        );
        $vehicle->deactivate();

        $this->expectException(DomainException::class);
        $vehicle->registerMileage(5000);
    }

    public function test_transfers_ownership_to_new_customer(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Ford',
            model: 'Ka',
            year: 2018,
            customerId: 'customer-1',
        );

        $vehicle->transferOwnership('customer-2');

        self::assertSame('customer-2', $vehicle->customerId());
    }

    public function test_rejects_transfer_to_empty_customer_id(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Ford',
            model: 'Ka',
            year: 2018,
            customerId: 'customer-1',
        );

        $this->expectException(InvalidValueException::class);
        $vehicle->transferOwnership('   ');
    }

    public function test_rejects_transfer_to_same_customer(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Ford',
            model: 'Ka',
            year: 2018,
            customerId: 'customer-1',
        );

        $this->expectException(DomainException::class);
        $vehicle->transferOwnership('customer-1');
    }

    public function test_rejects_transfer_on_inactive_vehicle(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Ford',
            model: 'Ka',
            year: 2018,
            customerId: 'customer-1',
        );
        $vehicle->deactivate();

        $this->expectException(DomainException::class);
        $vehicle->transferOwnership('customer-2');
    }

    public function test_deactivates_and_reactivates_vehicle(): void
    {
        $vehicle = Vehicle::create(
            licensePlate: LicensePlate::from('ABC1234'),
            brand: 'Hyundai',
            model: 'HB20',
            year: 2023,
        );

        $vehicle->deactivate();
        self::assertFalse($vehicle->isActive());

        $vehicle->activate();
        self::assertTrue($vehicle->isActive());
    }

    public function test_domain_exceptions_messages(): void
    {
        $alreadyExists = VehicleAlreadyExistsException::withLicensePlate('ABC1234');
        self::assertSame('Já existe um veículo cadastrado com a placa ABC1234.', $alreadyExists->getMessage());

        $notFoundById = VehicleNotFoundException::withId('uuid-1');
        self::assertSame('Veículo com ID uuid-1 não encontrado.', $notFoundById->getMessage());

        $notFoundByPlate = VehicleNotFoundException::withLicensePlate('ABC1234');
        self::assertSame('Veículo com placa ABC1234 não encontrado.', $notFoundByPlate->getMessage());
    }
}
