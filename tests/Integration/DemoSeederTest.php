<?php

declare(strict_types=1);

namespace Tests\Integration;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_profiles_catalog_and_an_order_awaiting_approval_idempotently(): void
    {
        config(['app.admin_seed_password' => 'senha-demo-123']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('services', 2);
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseCount('service_standard_parts', 2);
        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseCount('service_orders', 1);
        $this->assertDatabaseHas('service_orders', ['status' => 'AGUARDANDO_APROVACAO']);
        $this->assertDatabaseCount('service_order_services', 1);
        $this->assertDatabaseCount('service_order_parts', 2);

        foreach (['admin@oficina.local' => 'administrator', 'mecanico@oficina.local' => 'mechanic', 'cliente@oficina.local' => 'customer'] as $email => $role) {
            $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'senha-demo-123'])->assertOk();
            $this->assertDatabaseHas('users', ['email' => $email, 'role' => $role]);
        }

        $token = $this->postJson('/api/auth/login', ['email' => 'cliente@oficina.local', 'password' => 'senha-demo-123'])->json('access_token');
        $this->withToken($token)->getJson('/api/service-orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'AGUARDANDO_APROVACAO');
    }
}
