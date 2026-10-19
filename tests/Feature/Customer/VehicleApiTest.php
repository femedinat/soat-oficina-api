<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_full_vehicle_lifecycle_via_http_api(): void
    {
        $customer1 = '550e8400-e29b-41d4-a716-446655440000';
        $customer2 = '660e8400-e29b-41d4-a716-446655440001';

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

        // Placa duplicada deve retornar 422
        $this->postJson('/api/vehicles', [
            'license_plate' => 'BRA2E19',
            'brand' => 'Fiat',
            'model' => 'Toro',
            'year' => 2024,
        ], $this->headers)->assertStatus(422);

        // Placa inválida deve retornar 422
        $this->postJson('/api/vehicles', [
            'license_plate' => 'INVALID',
            'brand' => 'Fiat',
            'model' => 'Toro',
            'year' => 2024,
        ], $this->headers)->assertStatus(422);

        // 2. Fase 3: Consultas por ID, por Placa e Listagem
        $this->getJson("/api/vehicles/{$vehicleId}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicleId);

        $this->getJson('/api/vehicles/plate/bra-2e19', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicleId);

        $this->getJson("/api/vehicles?customer_id={$customer1}", $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/vehicles?only_active=false', $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');

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

        // Atualizar placa para uma já existente em outro veículo deve retornar 422
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
            ->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um veículo cadastrado com a placa DEF5G67.');
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

        $this->getJson('/api/vehicles/id-invalido', $this->headers)
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso ou rota não encontrada.');

        $this->putJson('/api/vehicles', [], $this->headers)
            ->assertStatus(405)
            ->assertJsonPath('message', 'Método HTTP não permitido para esta rota.');
    }
}
