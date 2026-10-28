<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterVehicleMileageRequest extends FormRequest
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
             * Nova quilometragem do veículo em km (deve ser maior ou igual à quilometragem atual).
             *
             * @example 25000
             */
            'mileage' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mileage' => 'quilometragem',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'min.numeric' => 'O campo :attribute deve ser no mínimo :min.',
        ];
    }
}
