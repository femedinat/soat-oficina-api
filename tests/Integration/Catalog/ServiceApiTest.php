<?php

declare(strict_types=1);

namespace Tests\Integration\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Integration\ApiActors;
use Tests\TestCase;

final class ServiceApiTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const string UNKNOWN = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

    public function test_routes_require_authentication(): void
    {
        $id = self::UNKNOWN;

        $this->getJson('/api/services')->assertUnauthorized();
        $this->getJson("/api/services/$id")->assertUnauthorized();
        $this->postJson('/api/services', [])->assertUnauthorized();
        $this->putJson("/api/services/$id", [])->assertUnauthorized();
        $this->patchJson("/api/services/$id/price", [])->assertUnauthorized();
        $this->deleteJson("/api/services/$id")->assertUnauthorized();
    }

    public function test_admin_registers_and_everyone_reads_catalog(): void
    {
        $response = $this->postJson('/api/services', [
            'code' => 'troca-oleo',
            'name' => 'Troca de óleo',
            'description' => 'Óleo e filtro',
            'category' => 'Preventiva',
            'labor_price' => '120.5',
            'estimated_minutes' => 60,
        ], $this->admin());

        $response->assertCreated()
            ->assertJsonPath('data.code', 'TROCA-OLEO')
            ->assertJsonPath('data.labor_price', '120.50')
            ->assertJsonPath('data.labor_price_cents', 12050)
            ->assertJsonPath('data.active', true);
        $id = (string) $response->json('data.id');

        $customerId = $this->createCustomer('52998224725');
        foreach ([$this->mechanic(), $this->customer($customerId)] as $headers) {
            $this->getJson('/api/services?search=óleo&category=preventiva', $headers)
                ->assertOk()
                ->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $id);
            $this->getJson("/api/services/$id", $headers)->assertOk()->assertJsonPath('data.name', 'Troca de óleo');
            $this->postJson('/api/services', ['code' => 'X1', 'name' => 'X', 'labor_price' => 1, 'estimated_minutes' => 1], $headers)->assertForbidden();
            $this->deleteJson("/api/services/$id", [], $headers)->assertForbidden();
        }
    }

    public function test_code_and_name_are_unique(): void
    {
        $this->createService('ALINHAMENTO', 'Alinhamento');

        $this->postJson('/api/services', ['code' => 'alinhamento', 'name' => 'Outro', 'labor_price' => 10, 'estimated_minutes' => 10], $this->admin())
            ->assertConflict();
        $this->postJson('/api/services', ['code' => 'ALINH-2', 'name' => 'ALINHAMENTO', 'labor_price' => 10, 'estimated_minutes' => 10], $this->admin())
            ->assertConflict();
        $this->postJson('/api/services', ['code' => 'A B', 'name' => 'Inválido', 'labor_price' => 10, 'estimated_minutes' => 10], $this->admin())
            ->assertUnprocessable();
        $this->postJson('/api/services', ['code' => 'OK', 'name' => 'Sem preço'], $this->admin())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['labor_price', 'estimated_minutes']);
    }

    public function test_updates_price_time_and_data(): void
    {
        $id = $this->createService('BALANCEAMENTO', 'Balanceamento', '50.00', 40);
        $other = $this->createService('ALINHAMENTO', 'Alinhamento');

        $this->putJson("/api/services/$id", ['name' => 'Balanceamento 4 rodas', 'category' => 'Rodas'], $this->admin())
            ->assertOk()
            ->assertJsonPath('data.name', 'Balanceamento 4 rodas')
            ->assertJsonPath('data.category', 'Rodas');
        $this->putJson("/api/services/$other", ['name' => 'balanceamento 4 RODAS'], $this->admin())->assertConflict();

        $this->patchJson("/api/services/$id/price", ['labor_price' => '65.90'], $this->admin())
            ->assertOk()
            ->assertJsonPath('data.labor_price', '65.90');
        $this->patchJson("/api/services/$id/estimated-time", ['estimated_minutes' => 50], $this->admin())
            ->assertOk()
            ->assertJsonPath('data.estimated_minutes', 50);
        $this->patchJson("/api/services/$id/estimated-time", ['estimated_minutes' => 0], $this->admin())->assertUnprocessable();
    }

    public function test_standard_parts_follow_inventory(): void
    {
        $service = $this->createService('TROCA-OLEO', 'Troca de óleo');
        $oil = $this->createPart('OLEO-5W30', '42.00', '20', 'L');
        $filter = $this->createPart('FILTRO-OLEO', '35.90', '5');

        $this->putJson("/api/services/$service/standard-parts", ['inventory_item_id' => $oil, 'quantity' => '4.5'], $this->admin())
            ->assertOk()
            ->assertJsonPath('data.standard_parts.0.quantity', '4.500');
        $this->putJson("/api/services/$service/standard-parts", ['inventory_item_id' => $filter, 'quantity' => '1'], $this->admin())
            ->assertOk()
            ->assertJsonCount(2, 'data.standard_parts');
        $this->putJson("/api/services/$service/standard-parts", ['inventory_item_id' => self::UNKNOWN, 'quantity' => '1'], $this->admin())
            ->assertNotFound();

        $this->deleteJson("/api/services/$service/standard-parts/$filter", [], $this->admin())
            ->assertOk()
            ->assertJsonCount(1, 'data.standard_parts');
        $this->deleteJson("/api/services/$service/standard-parts/$filter", [], $this->admin())->assertUnprocessable();

        // Política: peça inativada no Estoque deixa de ser padrão nos serviços.
        $this->deleteJson("/api/parts/$oil", [], $this->admin())->assertNoContent();
        $this->getJson("/api/services/$service", $this->admin())->assertOk()->assertJsonCount(0, 'data.standard_parts');
        $this->assertDatabaseMissing('service_standard_parts', ['inventory_item_id' => $oil]);

        $this->putJson("/api/services/$service/standard-parts", ['inventory_item_id' => $oil, 'quantity' => '1'], $this->admin())
            ->assertUnprocessable();
    }

    public function test_deactivation_hides_service_from_catalog(): void
    {
        $id = $this->createService('LAVAGEM', 'Lavagem');

        $this->deleteJson("/api/services/$id", [], $this->admin())->assertNoContent();
        $this->getJson('/api/services', $this->admin())->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/services?include_inactive=1', $this->admin())->assertOk()->assertJsonPath('data.0.active', false);
        $this->putJson("/api/services/$id/standard-parts", ['inventory_item_id' => $this->createPart('X-1', '1.00', '1'), 'quantity' => '1'], $this->admin())
            ->assertUnprocessable();

        $this->patchJson("/api/services/$id/activate", [], $this->admin())->assertOk()->assertJsonPath('data.active', true);
    }

    public function test_unknown_or_malformed_id_is_404(): void
    {
        $this->getJson('/api/services/'.self::UNKNOWN, $this->admin())->assertNotFound();
        $this->getJson('/api/services/123', $this->admin())->assertNotFound();
        $this->patchJson('/api/services/'.self::UNKNOWN.'/price', ['labor_price' => '1'], $this->admin())->assertNotFound();
    }
}
