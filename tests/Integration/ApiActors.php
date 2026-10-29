<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Support\Str;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Identity\Domain\Role;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/** Atores autenticados por JWT para os testes de API (um token novo por chamada). */
trait ApiActors
{
    protected function createCustomer(string $document, string $name = 'Maria Silva', bool $active = true): string
    {
        $customer = new CustomerModel;
        $customer->id = (string) Str::uuid();
        $customer->fill([
            'name' => $name,
            'document' => $document,
            'email' => Str::slug($name).'-'.Str::random(4).'@example.com',
            'active' => $active,
        ]);
        $customer->save();

        return $customer->id;
    }

    /** Guard e JWT memorizam usuário e token entre requisições do mesmo teste; sem isso o próximo token seria ignorado. */
    protected function guest(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();
    }

    /** @return array<string, string> */
    protected function as(Role $role, ?string $customerId = null): array
    {
        $this->guest();

        // Cliente tem um único usuário (users.customer_id é único).
        $user = $customerId === null
            ? User::factory()->create(['role' => $role])
            : User::query()->where('customer_id', $customerId)->first() ?? User::factory()->create(['role' => $role, 'customer_id' => $customerId]);

        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    /** @return array<string, string> */
    protected function admin(): array
    {
        return $this->as(Role::ADMINISTRATOR);
    }

    /** @return array<string, string> */
    protected function mechanic(): array
    {
        return $this->as(Role::MECHANIC);
    }

    /** @return array<string, string> */
    protected function customer(string $customerId): array
    {
        return $this->as(Role::CUSTOMER, $customerId);
    }

    protected function createPart(string $sku, string $price, string $quantity, string $unit = 'UN'): string
    {
        return (string) $this->postJson('/api/parts', [
            'sku' => $sku,
            'name' => 'Peça '.$sku,
            'type' => 'PECA',
            'unit' => $unit,
            'price' => $price,
            'initial_quantity' => $quantity,
            'minimum_quantity' => '0',
        ], $this->admin())->assertCreated()->json('data.id');
    }

    protected function createService(string $code, string $name, string $price = '120.00', int $minutes = 60): string
    {
        return (string) $this->postJson('/api/services', [
            'code' => $code,
            'name' => $name,
            'labor_price' => $price,
            'estimated_minutes' => $minutes,
        ], $this->admin())->assertCreated()->json('data.id');
    }
}
