<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Oficina\Customer\Application\CustomerContactInput;

/**
 * Validação de formato do payload para atualização exclusiva de contato (e-mail e telefone).
 */
final class UpdateCustomerContactRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** @example maria.novo@example.com */
            'email' => ['required', 'string', 'max:254'],
            /** Telefone com DDD. @example (11) 98765-4321 */
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function toInput(): CustomerContactInput
    {
        return new CustomerContactInput(
            email: (string) $this->string('email'),
            phone: $this->filled('phone') ? (string) $this->string('phone') : null,
        );
    }
}
