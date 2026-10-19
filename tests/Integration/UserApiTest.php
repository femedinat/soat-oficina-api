<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Identity\Domain\Role;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

final class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function headersFor(Role $role): array
    {
        $attributes = ['role' => $role, 'email' => $role->value.'@oficina.local'];

        if ($role->requiresCustomer()) {
            $linked = new CustomerModel;
            $linked->id = '9a1b2c3d-4e5f-4a6b-8c7d-0e1f2a3b4c5d';
            $linked->fill(['name' => 'Cliente Logado', 'document' => '11222333000181', 'email' => 'logado@example.com']);
            $linked->save();
            $attributes['customer_id'] = $linked->id;
        }

        $user = User::factory()->create($attributes);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    private function customer(string $document = '52998224725'): CustomerModel
    {
        $customer = new CustomerModel;
        $customer->id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';
        $customer->fill(['name' => 'Maria', 'document' => $document, 'email' => 'maria@example.com']);
        $customer->save();

        return $customer;
    }

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return [...['name' => 'João', 'email' => 'joao@oficina.local', 'password' => 'senha-forte-1', 'role' => 'mechanic'], ...$override];
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/users', $this->payload())->assertUnauthorized();
    }

    public function test_only_administrator_can_create_users(): void
    {
        $this->postJson('/api/users', $this->payload(), $this->headersFor(Role::MECHANIC))->assertForbidden();
        $this->postJson('/api/users', $this->payload(), $this->headersFor(Role::CUSTOMER))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'joao@oficina.local']);
    }

    public function test_administrator_creates_mechanic_who_can_login(): void
    {
        $this->postJson('/api/users', $this->payload(), $this->headersFor(Role::ADMINISTRATOR))
            ->assertCreated()
            ->assertJsonPath('data.role', 'mechanic')
            ->assertJsonPath('data.customer_id', null)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'joao@oficina.local', 'role' => 'mechanic']);
        $this->postJson('/api/auth/login', ['email' => 'joao@oficina.local', 'password' => 'senha-forte-1'])->assertOk();
    }

    public function test_creates_customer_user_with_one_to_one_link(): void
    {
        $customer = $this->customer();

        $this->postJson('/api/users', $this->payload(['role' => 'customer', 'customer_id' => $customer->id]), $this->headersFor(Role::ADMINISTRATOR))
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id);

        $user = User::query()->where('email', 'joao@oficina.local')->firstOrFail();
        self::assertSame($customer->id, $user->customer?->id);
        self::assertSame($user->id, $customer->user?->id);
    }

    public function test_second_user_for_same_customer_is_rejected(): void
    {
        $customer = $this->customer();
        $headers = $this->headersFor(Role::ADMINISTRATOR);
        $this->postJson('/api/users', $this->payload(['role' => 'customer', 'customer_id' => $customer->id]), $headers)->assertCreated();

        $this->postJson('/api/users', $this->payload(['role' => 'customer', 'customer_id' => $customer->id, 'email' => 'outro@oficina.local']), $headers)
            ->assertConflict();
    }

    public function test_validation_and_business_rules(): void
    {
        $headers = $this->headersFor(Role::ADMINISTRATOR);

        $this->postJson('/api/users', [], $headers)->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
        $this->postJson('/api/users', $this->payload(['role' => 'root']), $headers)->assertUnprocessable()->assertJsonValidationErrors(['role']);
        $this->postJson('/api/users', $this->payload(['password' => 'curta']), $headers)->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->postJson('/api/users', $this->payload(['role' => 'customer']), $headers)->assertUnprocessable();
        $this->postJson('/api/users', $this->payload(['customer_id' => '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11']), $headers)->assertUnprocessable();
        $this->postJson('/api/users', $this->payload(['role' => 'customer', 'customer_id' => '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11']), $headers)->assertUnprocessable();
        $this->postJson('/api/users', $this->payload(['email' => 'invalido']), $headers)->assertUnprocessable();
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $headers = $this->headersFor(Role::ADMINISTRATOR);
        $this->postJson('/api/users', $this->payload(), $headers)->assertCreated();

        $this->postJson('/api/users', $this->payload(['email' => 'JOAO@oficina.local']), $headers)->assertConflict();
    }

    public function test_database_enforces_role_customer_consistency(): void
    {
        $this->expectException(QueryException::class);

        User::factory()->create(['role' => Role::CUSTOMER, 'customer_id' => null]);
    }

    public function test_deleting_customer_removes_linked_user(): void
    {
        $customer = $this->customer();
        $this->postJson('/api/users', $this->payload(['role' => 'customer', 'customer_id' => $customer->id]), $this->headersFor(Role::ADMINISTRATOR))->assertCreated();

        $customer->delete();

        $this->assertDatabaseMissing('users', ['email' => 'joao@oficina.local']);
    }

    public function test_me_exposes_role(): void
    {
        $this->getJson('/api/auth/me', $this->headersFor(Role::MECHANIC))->assertOk()->assertJsonPath('role', 'mechanic');
    }
}
