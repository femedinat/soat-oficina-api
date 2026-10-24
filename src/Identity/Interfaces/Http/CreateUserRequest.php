<?php

declare(strict_types=1);

namespace Oficina\Identity\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Oficina\Identity\Application\CreateUserInput;
use Oficina\Identity\Domain\Role;
use Oficina\Identity\Domain\SystemUser;

/** Validação de formato; as regras de negócio (perfil x cliente, unicidade) ficam no domínio. */
final class CreateUserRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            /** @example João Mecânico */
            'name' => ['required', 'string', 'max:255'],
            /** @example joao@oficina.local */
            'email' => ['required', 'string', 'max:254'],
            /** Mínimo de 8 caracteres. */
            'password' => ['required', 'string', 'min:'.SystemUser::PASSWORD_MIN_LENGTH, 'max:255'],
            /** Perfil de acesso. */
            'role' => ['required', Rule::enum(Role::class)],
            /** Obrigatório (e somente permitido) para o perfil `customer`. Id de um cliente já cadastrado. */
            'customer_id' => ['nullable', 'uuid'],
        ];
    }

    public function toInput(): CreateUserInput
    {
        return new CreateUserInput(
            name: (string) $this->string('name'),
            email: (string) $this->string('email'),
            password: (string) $this->string('password'),
            role: Role::from((string) $this->string('role')),
            customerId: $this->filled('customer_id') ? (string) $this->string('customer_id') : null,
        );
    }
}
