<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Identity\Domain\Role;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '529.982.247-25';

    private const CNPJ = '11.222.333/0001-81';

    /** @var array<string, string> */
    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['email' => 'admin@oficina.local', 'password' => 'segredo-123']);
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
            ]);
            $linked->save();
            $attributes['customer_id'] = $linked->id;
        }

        $user = User::factory()->create($attributes);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    /** @return array<string, string> */
    private function payload(string $document = self::CPF): array
    {
        return ['name' => 'Maria da Silva', 'document' => $document, 'email' => 'maria@example.com', 'phone' => '(11) 98765-4321'];
    }

    public function test_all_routes_require_authentication(): void
    {
        $id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

        $this->getJson('/api/customers')->assertUnauthorized();
        $this->postJson('/api/customers', $this->payload())->assertUnauthorized();
        $this->getJson("/api/customers/$id")->assertUnauthorized();
        $this->putJson("/api/customers/$id", $this->payload())->assertUnauthorized();
        $this->deleteJson("/api/customers/$id")->assertUnauthorized();
    }

    public function test_non_administrators_receive_forbidden_on_write_routes(): void
    {
        $existing = new CustomerModel;
        $existing->id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';
        $existing->fill(['name' => 'Original', 'document' => '52998224725', 'email' => 'orig@example.com']);
        $existing->save();

        foreach ([Role::CUSTOMER, Role::MECHANIC] as $role) {
            $headers = $this->headersFor($role);

            $this->postJson('/api/customers', $this->payload(self::CNPJ), $headers)->assertForbidden();
            $this->putJson("/api/customers/{$existing->id}", [...$this->payload(), 'name' => 'Alterado'], $headers)->assertForbidden();
            $this->deleteJson("/api/customers/{$existing->id}", [], $headers)->assertForbidden();
        }

        $this->assertDatabaseHas('customers', ['id' => $existing->id, 'name' => 'Original']);
        $this->assertDatabaseMissing('customers', ['document' => '11222333000181']);
    }

    public function test_customer_role_receives_forbidden_on_read_routes(): void
    {
        $existing = new CustomerModel;
        $existing->id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';
        $existing->fill(['name' => 'Original', 'document' => '52998224725', 'email' => 'orig@example.com']);
        $existing->save();

        $headers = $this->headersFor(Role::CUSTOMER);

        $this->getJson('/api/customers', $headers)->assertForbidden();
        $this->getJson("/api/customers/{$existing->id}", $headers)->assertForbidden();
    }

    public function test_mechanic_can_read_customers(): void
    {
        $existing = new CustomerModel;
        $existing->id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';
        $existing->fill(['name' => 'Original', 'document' => '52998224725', 'email' => 'orig@example.com']);
        $existing->save();

        $headers = $this->headersFor(Role::MECHANIC);

        $this->getJson('/api/customers', $headers)->assertOk();
        $this->getJson("/api/customers/{$existing->id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Original');
    }

    public function test_lists_customers_filtered_by_document(): void
    {
        $this->postJson('/api/customers', $this->payload(), $this->headers)->assertCreated();
        $this->postJson('/api/customers', [...$this->payload(self::CNPJ), 'name' => 'Auto Peças'], $this->headers)->assertCreated();

        // Admin consulta por CPF formatado
        $this->getJson('/api/customers?document='.urlencode(self::CPF), $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Maria da Silva')
            ->assertJsonPath('meta.total', 1);

        // Mecânico consulta por CNPJ sem máscara (fluxo de OS)
        $mechanicHeaders = $this->headersFor(Role::MECHANIC);
        $this->getJson('/api/customers?document=11222333000181', $mechanicHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Auto Peças');

        // Documento não cadastrado
        $this->getJson('/api/customers?document=11144477735', $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        // Documento com formato inválido
        $this->getJson('/api/customers?document=invalido', $this->headers)
            ->assertUnprocessable();
    }

    public function test_creates_customer_with_cpf(): void
    {
        $response = $this->postJson('/api/customers', $this->payload(), $this->headers);

        $response->assertCreated()
            ->assertJsonPath('data.document', '52998224725')
            ->assertJsonPath('data.document_type', 'CPF')
            ->assertJsonPath('data.phone', '11987654321');
        $this->assertDatabaseHas('customers', ['id' => $response->json('data.id'), 'document' => '52998224725']);
    }

    public function test_creates_customer_with_cnpj(): void
    {
        $this->postJson('/api/customers', $this->payload(self::CNPJ), $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.document_type', 'CNPJ');
    }

    public function test_requires_document(): void
    {
        $payload = $this->payload();
        unset($payload['document']);

        $this->postJson('/api/customers', $payload, $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['document']);
    }

    public function test_rejects_invalid_document(): void
    {
        $this->postJson('/api/customers', $this->payload('111.111.111-11'), $this->headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'CPF/CNPJ inválido.');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_rejects_invalid_email_and_phone(): void
    {
        $this->postJson('/api/customers', [...$this->payload(), 'email' => 'nao-e-email'], $this->headers)->assertUnprocessable();
        $this->postJson('/api/customers', [...$this->payload(), 'phone' => '123'], $this->headers)->assertUnprocessable();
    }

    public function test_rejects_duplicate_document_regardless_of_mask(): void
    {
        $this->postJson('/api/customers', $this->payload(), $this->headers)->assertCreated();

        $this->postJson('/api/customers', $this->payload('52998224725'), $this->headers)
            ->assertConflict();
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_shows_customer(): void
    {
        $id = $this->postJson('/api/customers', $this->payload(), $this->headers)->json('data.id');

        $this->getJson("/api/customers/$id", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'Maria da Silva');
    }

    public function test_show_unknown_returns_404_and_malformed_id_returns_422(): void
    {
        $this->getJson('/api/customers/2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11', $this->headers)->assertNotFound();
        $this->getJson('/api/customers/abc', $this->headers)->assertUnprocessable();
    }

    public function test_updates_customer(): void
    {
        $id = $this->postJson('/api/customers', $this->payload(), $this->headers)->json('data.id');

        $this->putJson("/api/customers/$id", [...$this->payload(self::CNPJ), 'name' => 'Oficina Ltda'], $this->headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Oficina Ltda')
            ->assertJsonPath('data.document_type', 'CNPJ');
        $this->assertDatabaseHas('customers', ['id' => $id, 'document' => '11222333000181']);
    }

    public function test_update_rejects_document_of_another_customer(): void
    {
        $this->postJson('/api/customers', $this->payload(self::CNPJ), $this->headers)->assertCreated();
        $id = $this->postJson('/api/customers', $this->payload(), $this->headers)->json('data.id');

        $this->putJson("/api/customers/$id", $this->payload(self::CNPJ), $this->headers)->assertConflict();
    }

    public function test_update_unknown_returns_404(): void
    {
        $this->putJson('/api/customers/2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11', $this->payload(), $this->headers)->assertNotFound();
    }

    public function test_deletes_customer(): void
    {
        $id = $this->postJson('/api/customers', $this->payload(), $this->headers)->json('data.id');

        $this->deleteJson("/api/customers/$id", [], $this->headers)->assertNoContent();

        $this->assertSoftDeleted('customers', ['id' => $id]);
        $this->deleteJson("/api/customers/$id", [], $this->headers)->assertNotFound();
    }

    public function test_lists_customers_paginated(): void
    {
        $this->postJson('/api/customers', $this->payload(), $this->headers)->assertCreated();
        $this->postJson('/api/customers', [...$this->payload(self::CNPJ), 'name' => 'Auto Peças'], $this->headers)->assertCreated();

        $this->getJson('/api/customers?per_page=1&page=2', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.name', 'Maria da Silva');
    }

    public function test_list_validates_pagination_params(): void
    {
        $this->getJson('/api/customers?per_page=1000', $this->headers)->assertUnprocessable();
    }
}
