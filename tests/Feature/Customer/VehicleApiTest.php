<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Oficina\Customer\Domain\Entity\Vehicle;
use Oficina\Customer\Domain\Exception\VehicleAlreadyExistsException;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Customer\Infrastructure\Persistence\Eloquent\EloquentVehicleRepository;
use Oficina\Identity\Domain\Role;
use Oficina\Shared\Domain\ValueObject\LicensePlate;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'admin@oficina.local',
            'password' => 'segredo-123',
        ]);
        $token = JWTAuth::fromUser($user);
        $this->headers = ['Authorization' => 'Bearer '.$token];
    }

    /** @return array<string, string> */
    private function headersFor(Role $role): array
    {
        $attributes = ['role' => $role];

        if ($role->requiresCustomer()) {
            $linked = new CustomerModel;
            $linked->id = (string) Str::uuid();
            $linked->fill([
                'name' => 'Cliente Logado',
                'document' => (string) mt_rand(10000000000, 99999999999),
                'email' => 'logado-'.Str::random(6).'@example.com',
                'active' => true,
            ]);
            $linked->save();
            $attributes['customer_id'] = $linked->id;
        }

        $user = User::factory()->create($attributes);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function createCustomer(string $id, string $document, bool $active = true): CustomerModel
    {
        $customer = new CustomerModel;
        $customer->id = $id;
        $customer->fill([
            'name' => 'Cliente '.$id,
            'document' => $document,
            'email' => 'cliente-'.substr($document, 0, 5).'@example.com',
            'active' => $active,
        ]);
        $customer->save();

        return $customer;
    }

    public function test_all_vehicle_routes_require_authentication(): void
    {
        $id = '550e8400-e29b-41d4-a716-446655440000';

        $this->getJson('/api/vehicles')->assertUnauthorized();
        $this->postJson('/api/vehicles', [])->assertUnauthorized();
        $this->getJson('/api/vehicles/plate/BRA2E19')->assertUnauthorized();
        $this->getJson("/api/vehicles/{$id}")->assertUnauthorized();
        $this->putJson("/api/vehicles/{$id}", [])->assertUnauthorized();
        $this->patchJson("/api/vehicles/{$id}/mileage", [])->assertUnauthorized();
        $this->patchJson("/api/vehicles/{$id}/transfer", [])->assertUnauthorized();
        $this->patchJson("/api/vehicles/{$id}/activate")->assertUnauthorized();
        $this->deleteJson("/api/vehicles/{$id}")->assertUnauthorized();
    }

    public function test_non_administrators_receive_forbidden_on_write_routes(): void
    {
        $vehicleId = $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
        ], $this->headers)->json('data.id');

        foreach ([Role::CUSTOMER, Role::MECHANIC] as $role) {
            $headers = $this->headersFor($role);

            $this->postJson('/api/vehicles', [
                'license_plate' => 'ABC1234',
                'brand' => 'Fiat',
                'model' => 'Uno',
                'year' => 2020,
            ], $headers)->assertForbidden();

            $this->putJson("/api/vehicles/{$vehicleId}", [
                'brand' => 'Volkswagen',
                'model' => 'Nivus Highline',
                'year' => 2024,
            ], $headers)->assertForbidden();

            $this->patchJson("/api/vehicles/{$vehicleId}/mileage", [
                'mileage' => 5000,
            ], $headers)->assertForbidden();

            $this->patchJson("/api/vehicles/{$vehicleId}/transfer", [
                'customer_id' => '550e8400-e29b-41d4-a716-446655440000',
            ], $headers)->assertForbidden();

            $this->patchJson("/api/vehicles/{$vehicleId}/activate", [], $headers)->assertForbidden();
            $this->deleteJson("/api/vehicles/{$vehicleId}", [], $headers)->assertForbidden();
        }
    }

    public function test_customer_role_receives_forbidden_on_read_routes(): void
    {
        $vehicleId = $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
        ], $this->headers)->json('data.id');

        $headers = $this->headersFor(Role::CUSTOMER);

        $this->getJson('/api/vehicles', $headers)->assertForbidden();
        $this->getJson("/api/vehicles/{$vehicleId}", $headers)->assertForbidden();
        $this->getJson('/api/vehicles/plate/BRA2E19', $headers)->assertForbidden();
    }

    public function test_mechanic_can_read_vehicles(): void
    {
        $vehicleId = $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
        ], $this->headers)->json('data.id');

        $headers = $this->headersFor(Role::MECHANIC);

        $this->getJson('/api/vehicles', $headers)->assertOk();
        $this->getJson("/api/vehicles/{$vehicleId}", $headers)
            ->assertOk()
            ->assertJsonPath('data.license_plate', 'BRA2E19');
        $this->getJson('/api/vehicles/plate/BRA2E19', $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicleId);
    }

    public function test_rejects_nonexistent_or_inactive_customer_on_create_and_transfer(): void
    {
        $activeCustomer = $this->createCustomer('550e8400-e29b-41d4-a716-446655440000', '52998224725', true);
        $inactiveCustomer = $this->createCustomer('660e8400-e29b-41d4-a716-446655440001', '11222333000181', false);
        $unknownCustomerId = '770e8400-e29b-41d4-a716-446655440002';

        // Criar com cliente inexistente -> 404
        $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
            'customer_id' => $unknownCustomerId,
        ], $this->headers)->assertNotFound();

        // Criar com cliente inativo -> 422
        $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
            'customer_id' => $inactiveCustomer->id,
        ], $this->headers)->assertStatus(422);

        $vehicleId = $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
            'customer_id' => $activeCustomer->id,
        ], $this->headers)->json('data.id');

        // Transferir para cliente inexistente -> 404
        $this->patchJson("/api/vehicles/{$vehicleId}/transfer", [
            'customer_id' => $unknownCustomerId,
        ], $this->headers)->assertNotFound();

        // Transferir para cliente inativo -> 422
        $this->patchJson("/api/vehicles/{$vehicleId}/transfer", [
            'customer_id' => $inactiveCustomer->id,
        ], $this->headers)->assertStatus(422);
    }

    public function test_full_vehicle_lifecycle_via_http_api(): void
    {
        $customer1 = '550e8400-e29b-41d4-a716-446655440000';
        $customer2 = '660e8400-e29b-41d4-a716-446655440001';
        $this->createCustomer($customer1, '52998224725');
        $this->createCustomer($customer2, '11222333000181');

        // 1. Fase 1: Cadastrar Novo Veículo (POST /api/vehicles)
        $createResponse = $this->postJson('/api/vehicles', [
            'license_plate' => 'bra-2e19',
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
            'customer_id' => $customer1,
            'color' => 'Azul',
            'mileage' => 1000,
        ], $this->headers);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.license_plate', 'BRA2E19')
            ->assertJsonPath('data.is_mercosul', true)
            ->assertJsonPath('data.brand', 'Volkswagen')
            ->assertJsonPath('data.model', 'Nivus')
            ->assertJsonPath('data.year', 2024)
            ->assertJsonPath('data.customer_id', $customer1)
            ->assertJsonPath('data.color', 'Azul')
            ->assertJsonPath('data.mileage', 1000)
            ->assertJsonPath('data.active', true);

        /** @var string $vehicleId */
        $vehicleId = $createResponse->json('data.id');

        // Placa duplicada deve retornar 409
        $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Fiat',
            'model' => 'Toro',
            'year' => 2024,
        ], $this->headers)->assertConflict();

        // Placa inválida deve retornar 422
        $this->postJson('/api/vehicles', [
            'license_plate' => 'INVALID',
            'brand' => 'Fiat',
            'model' => 'Toro',
            'year' => 2024,
        ], $this->headers)->assertStatus(422);

        // 2. Fase 3: Consultas por ID, por Placa e Listagem Paginada
        $this->getJson("/api/vehicles/{$vehicleId}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicleId);

        $this->getJson('/api/vehicles/plate/bra-2e19', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicleId);

        $this->getJson("/api/vehicles?customer_id={$customer1}", $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.page', 1);

        $this->getJson('/api/vehicles?only_active=false', $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        // 3. Fase 2: Atualizar Dados e Registrar Quilometragem
        $this->putJson("/api/vehicles/{$vehicleId}", [
            'brand' => 'Volkswagen',
            'model' => 'Nivus Highline',
            'year' => 2024,
            'color' => 'Cinza Moonstone',
            'license_plate' => 'ABC-1234',
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.model', 'Nivus Highline')
            ->assertJsonPath('data.color', 'Cinza Moonstone')
            ->assertJsonPath('data.license_plate', 'ABC1234')
            ->assertJsonPath('data.is_mercosul', false);

        $this->patchJson("/api/vehicles/{$vehicleId}/mileage", [
            'mileage' => 5500,
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.mileage', 5500);

        // Quilometragem menor que a atual deve retornar 422
        $this->patchJson("/api/vehicles/{$vehicleId}/mileage", [
            'mileage' => 3000,
        ], $this->headers)->assertStatus(422);

        // 4. Fase 4: Transferência de Proprietário e Inativação
        $this->patchJson("/api/vehicles/{$vehicleId}/transfer", [
            'customer_id' => $customer2,
        ], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.customer_id', $customer2);

        $this->deleteJson("/api/vehicles/{$vehicleId}", [], $this->headers)
            ->assertNoContent();

        // Veículo inativo não permite atualização cadastral
        $this->putJson("/api/vehicles/{$vehicleId}", [
            'brand' => 'Volkswagen',
            'model' => 'Nivus',
            'year' => 2024,
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não é possível atualizar os dados de um veículo inativo.');

        $this->getJson('/api/vehicles', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active', false);

        $this->getJson('/api/vehicles?only_active=', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/vehicles?only_active=true', $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/vehicles?only_active=false', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active', false);

        $this->patchJson("/api/vehicles/{$vehicleId}/activate", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicleId)
            ->assertJsonPath('data.active', true);

        $this->getJson('/api/vehicles?only_active=true', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.active', true);

        // Buscar veículo inexistente por ID ou Placa deve retornar 404 em português
        $this->getJson('/api/vehicles/00000000-0000-0000-0000-000000000000', $this->headers)
            ->assertNotFound()
            ->assertJsonPath('message', 'Veículo com ID 00000000-0000-0000-0000-000000000000 não encontrado.');

        $this->getJson('/api/vehicles/plate/XYZ9Z99', $this->headers)
            ->assertNotFound()
            ->assertJsonPath('message', 'Veículo com placa XYZ9Z99 não encontrado.');

        // Atualizar placa para uma já existente em outro veículo deve retornar 409
        $this->postJson('/api/vehicles', [
            'license_plate' => 'DEF5G67',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2023,
        ], $this->headers)->assertCreated();

        $this->putJson("/api/vehicles/{$vehicleId}", [
            'brand' => 'Volkswagen',
            'model' => 'Nivus Highline',
            'year' => 2024,
            'license_plate' => 'DEF5G67',
        ], $this->headers)
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe um veículo cadastrado com a placa DEF5G67.');
    }

    public function test_repository_translates_unique_constraint_violation_to_conflict(): void
    {
        $repository = new EloquentVehicleRepository;

        $repository->save(Vehicle::create(
            licensePlate: LicensePlate::from('BRA2E19'),
            brand: 'Volkswagen',
            model: 'Nivus',
            year: 2024,
        ));

        $this->expectException(VehicleAlreadyExistsException::class);
        $repository->save(Vehicle::create(
            licensePlate: LicensePlate::from('BRA2E19'),
            brand: 'Fiat',
            model: 'Toro',
            year: 2024,
        ));
    }

    public function test_returns_validation_and_route_errors_in_portuguese(): void
    {
        $this->postJson('/api/vehicles', [], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'O campo placa é obrigatório. (e mais 3 erros)')
            ->assertJsonPath('errors.license_plate.0', 'O campo placa é obrigatório.')
            ->assertJsonPath('errors.brand.0', 'O campo marca é obrigatório.')
            ->assertJsonPath('errors.model.0', 'O campo modelo é obrigatório.')
            ->assertJsonPath('errors.year.0', 'O campo ano é obrigatório.');

        $maxYear = (int) date('Y') + 1;
        $this->postJson('/api/vehicles', [
            'license_plate' => 'ABC1D23',
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'year' => $maxYear + 1,
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.year.0', "O campo ano não pode ser maior do que {$maxYear}.");

        $this->getJson('/api/vehicles?customer_id=uuid-invalido', $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', 'O campo identificador do cliente deve ser um UUID válido.');

        $this->getJson('/api/vehicles?per_page=1000', $this->headers)
            ->assertStatus(422);

        $this->getJson('/api/vehicles/id-invalido', $this->headers)
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso ou rota não encontrada.');

        $this->putJson('/api/vehicles', [], $this->headers)
            ->assertStatus(405)
            ->assertJsonPath('message', 'Método HTTP não permitido para esta rota.');
    }
}
