<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class TransferVehicleOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Identificador UUID do novo cliente proprietário.
             *
             * @example 0195f3a0-1234-7000-8000-000000000002
             */
            'customer_id' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'identificador do cliente',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'uuid' => 'O campo :attribute deve ser um UUID válido.',
        ];
    }
}
