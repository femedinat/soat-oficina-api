<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Oficina\Customer\Application\CustomerInput;

/**
 * Validação de formato do payload. A validade do CPF/CNPJ e demais regras de negócio
 * são garantidas pelo domínio (Value Objects), não aqui.
 */
final class CustomerRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** Nome completo ou razão social. @example Maria da Silva */
            'name' => ['required', 'string', 'max:150'],
            /** CPF ou CNPJ (com ou sem máscara; aceita CNPJ alfanumérico). @example 529.982.247-25 */
            'document' => ['required', 'string', 'max:18'],
            /** @example maria@example.com */
            'email' => ['required', 'string', 'max:254'],
            /** Telefone com DDD. @example (11) 98765-4321 */
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function toInput(): CustomerInput
    {
        return new CustomerInput(
            name: (string) $this->string('name'),
            document: (string) $this->string('document'),
            email: (string) $this->string('email'),
            phone: $this->filled('phone') ? (string) $this->string('phone') : null,
        );
    }
}
