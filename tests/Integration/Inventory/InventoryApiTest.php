<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Identity\Domain\Role;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private const UNKNOWN_ID = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

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
            ]);
            $linked->save();
            $attributes['customer_id'] = $linked->id;
        }

        $user = User::factory()->create($attributes);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    /** @return array<string, string> */
    private function admin(): array
    {
        return $this->headersFor(Role::ADMINISTRATOR);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'FLT-OL-001',
            'name' => 'Filtro de óleo',
            'description' => 'Filtro rosqueável',
            'manufacturer' => 'Tecfil',
            'type' => 'PECA',
            'unit' => 'UN',
            'price' => '45.90',
            'initial_quantity' => '10',
            'minimum_quantity' => '3',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createItem(array $overrides = []): string
    {
        $id = (string) $this->postJson('/api/parts', $this->payload($overrides), $this->admin())
            ->assertCreated()
            ->json('data.id');

        // Guard e JWT memorizam usuário e token entre requisições do mesmo teste; sem isso o próximo token seria ignorado.
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return $id;
    }

    public function test_all_routes_require_authentication(): void
    {
        $id = self::UNKNOWN_ID;

        $this->getJson('/api/parts')->assertUnauthorized();
        $this->getJson('/api/parts/restock')->assertUnauthorized();
        $this->getJson("/api/parts/$id")->assertUnauthorized();
        $this->postJson('/api/parts', $this->payload())->assertUnauthorized();
        $this->putJson("/api/parts/$id", [])->assertUnauthorized();
        $this->patchJson("/api/parts/$id/price", ['price' => '1.00'])->assertUnauthorized();
        $this->postJson("/api/parts/$id/receipts", ['quantity' => '1'])->assertUnauthorized();
        $this->postJson("/api/parts/$id/adjustments", [])->assertUnauthorized();
        $this->getJson("/api/parts/$id/movements")->assertUnauthorized();
        $this->patchJson("/api/parts/$id/activate")->assertUnauthorized();
        $this->deleteJson("/api/parts/$id")->assertUnauthorized();
    }

    public function test_request_without_json_accept_header_gets_401_json_instead_of_500(): void
    {
        $this->get('/api/parts')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_customer_cannot_access_parts(): void
    {
        $headers = $this->headersFor(Role::CUSTOMER);

        $this->getJson('/api/parts', $headers)->assertForbidden();
        $this->getJson('/api/parts/'.self::UNKNOWN_ID, $headers)->assertForbidden();
        $this->postJson('/api/parts', $this->payload(), $headers)->assertForbidden();
    }

    public function test_mechanic_reads_but_cannot_change_stock(): void
    {
        $id = $this->createItem();
        $mechanic = $this->headersFor(Role::MECHANIC);

        $this->getJson('/api/parts', $mechanic)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/parts/$id", $mechanic)->assertOk()->assertJsonPath('data.sku', 'FLT-OL-001');
        $this->getJson('/api/parts/restock', $mechanic)->assertOk();

        $this->postJson('/api/parts', $this->payload(['sku' => 'OUTRO']), $mechanic)->assertForbidden();
        $this->putJson("/api/parts/$id", $this->payload(), $mechanic)->assertForbidden();
        $this->patchJson("/api/parts/$id/price", ['price' => '1.00'], $mechanic)->assertForbidden();
        $this->postJson("/api/parts/$id/receipts", ['quantity' => '1'], $mechanic)->assertForbidden();
        $this->postJson("/api/parts/$id/adjustments", ['counted_quantity' => '1', 'reason' => 'x'], $mechanic)->assertForbidden();
        $this->getJson("/api/parts/$id/movements", $mechanic)->assertForbidden();
        $this->patchJson("/api/parts/$id/activate", [], $mechanic)->assertForbidden();
        $this->deleteJson("/api/parts/$id", [], $mechanic)->assertForbidden();
    }

    public function test_administrator_registers_part_with_initial_stock(): void
    {
        $this->postJson('/api/parts', $this->payload(['sku' => 'flt-ol-002']), $this->admin())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'FLT-OL-002')
            ->assertJsonPath('data.type', 'PECA')
            ->assertJsonPath('data.unit', 'UN')
            ->assertJsonPath('data.price', '45.90')
            ->assertJsonPath('data.price_cents', 4590)
            ->assertJsonPath('data.physical_quantity', '10.000')
            ->assertJsonPath('data.available_quantity', '10.000')
            ->assertJsonPath('data.below_minimum', false)
            ->assertJsonPath('data.active', true);
    }

    public function test_duplicate_sku_returns_conflict(): void
    {
        $this->createItem();

        $this->postJson('/api/parts', $this->payload(['sku' => 'flt-ol-001']), $this->admin())->assertConflict();
    }

    public function test_invalid_payload_returns_validation_errors(): void
    {
        $this->postJson('/api/parts', ['type' => 'MOTOR', 'unit' => 'CX', 'price' => '-1'], $this->admin())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name', 'type', 'unit', 'price']);

        $this->postJson('/api/parts', $this->payload(['initial_quantity' => '1.5']), $this->admin())
            ->assertUnprocessable();
    }

    public function test_unknown_part_returns_not_found(): void
    {
        $admin = $this->admin();

        $this->getJson('/api/parts/'.self::UNKNOWN_ID, $admin)->assertNotFound();
        $this->getJson('/api/parts/nao-e-uuid', $admin)->assertNotFound();
        $this->patchJson('/api/parts/'.self::UNKNOWN_ID.'/price', ['price' => '10.00'], $admin)->assertNotFound();
        $this->deleteJson('/api/parts/nao-e-uuid', [], $admin)->assertNotFound();
    }

    public function test_administrator_updates_data_and_price(): void
    {
        $id = $this->createItem();
        $admin = $this->admin();

        $this->putJson("/api/parts/$id", [
            'name' => 'Filtro de óleo premium',
            'description' => null,
            'manufacturer' => 'Mann',
            'type' => 'PECA',
            'unit' => 'UN',
            'minimum_quantity' => '5',
        ], $admin)
            ->assertOk()
            ->assertJsonPath('data.name', 'Filtro de óleo premium')
            ->assertJsonPath('data.manufacturer', 'Mann')
            ->assertJsonPath('data.minimum_quantity', '5.000');

        $this->patchJson("/api/parts/$id/price", ['price' => '49.90'], $admin)
            ->assertOk()
            ->assertJsonPath('data.price_cents', 4990);

        $this->putJson("/api/parts/$id", ['name' => 'X', 'type' => 'PECA', 'unit' => 'L'], $admin)
            ->assertUnprocessable();
    }

    public function test_receipts_and_adjustments_appear_in_movements_and_restock(): void
    {
        $id = $this->createItem();
        $admin = $this->admin();

        $this->postJson("/api/parts/$id/receipts", ['quantity' => '5', 'reference' => 'NF-123'], $admin)
            ->assertOk()
            ->assertJsonPath('data.physical_quantity', '15.000');

        $this->postJson("/api/parts/$id/adjustments", ['counted_quantity' => '2', 'reason' => 'Contagem mensal'], $admin)
            ->assertOk()
            ->assertJsonPath('data.physical_quantity', '2.000')
            ->assertJsonPath('data.below_minimum', true);

        $this->postJson("/api/parts/$id/adjustments", ['counted_quantity' => '2'], $admin)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/parts/$id/receipts", ['quantity' => '0'], $admin)->assertUnprocessable();

        $this->getJson("/api/parts/$id/movements", $admin)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.1.reference', 'NF-123');

        $this->getJson('/api/parts/restock', $admin)
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_deactivate_hides_from_catalog_and_activate_restores(): void
    {
        $id = $this->createItem();
        $this->createItem(['sku' => 'OLEO-5W30', 'name' => 'Óleo 5W30', 'type' => 'INSUMO', 'unit' => 'L', 'initial_quantity' => '20.5']);
        $admin = $this->admin();

        $this->deleteJson("/api/parts/$id", [], $admin)->assertNoContent();

        $this->getJson('/api/parts', $admin)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/parts?include_inactive=1', $admin)->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/parts?type=insumo&search=5w30', $admin)
            ->assertOk()
            ->assertJsonPath('data.0.available_quantity', '20.500');

        $this->postJson("/api/parts/$id/receipts", ['quantity' => '1'], $admin)->assertUnprocessable();

        $this->patchJson("/api/parts/$id/activate", [], $admin)
            ->assertOk()
            ->assertJsonPath('data.active', true);
    }
}
