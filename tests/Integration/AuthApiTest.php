<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@oficina.local', 'password' => 'segredo-123']);
    }

    public function test_login_returns_jwt(): void
    {
        $this->admin();

        $response = $this->postJson('/api/auth/login', ['email' => 'admin@oficina.local', 'password' => 'segredo-123']);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('expires_in', 3600);
        self::assertCount(3, explode('.', $response->json('access_token')));
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $this->admin();

        $this->postJson('/api/auth/login', ['email' => 'ADMIN@oficina.local', 'password' => 'segredo-123'])->assertOk();
    }

    public function test_login_rejects_wrong_password_and_unknown_user_with_same_message(): void
    {
        $this->admin();

        $wrong = $this->postJson('/api/auth/login', ['email' => 'admin@oficina.local', 'password' => 'errada']);
        $unknown = $this->postJson('/api/auth/login', ['email' => 'x@oficina.local', 'password' => 'errada']);

        $wrong->assertUnauthorized()->assertJsonPath('message', 'Credenciais inválidas.');
        $unknown->assertUnauthorized()->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_login_validates_payload(): void
    {
        $this->postJson('/api/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@oficina.local', 'password' => 'errada'])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', ['email' => 'x@oficina.local', 'password' => 'errada'])->assertStatus(429);
    }

    public function test_protected_route_requires_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/customers')->assertUnauthorized();
    }

    public function test_protected_route_rejects_invalid_token(): void
    {
        $this->getJson('/api/customers', ['Authorization' => 'Bearer token.invalido.aqui'])->assertUnauthorized();
    }

    public function test_me_returns_authenticated_administrator(): void
    {
        $this->admin();
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@oficina.local', 'password' => 'segredo-123'])->json('access_token');

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('email', 'admin@oficina.local')
            ->assertJsonMissingPath('password');
    }

    public function test_logout_invalidates_token(): void
    {
        $this->admin();
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@oficina.local', 'password' => 'segredo-123'])->json('access_token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->postJson('/api/auth/logout', [], $headers)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me', $headers)->assertUnauthorized();
    }
}
