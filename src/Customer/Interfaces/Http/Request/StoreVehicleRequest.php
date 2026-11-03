<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class StoreVehicleRequest extends FormRequest
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
        $maxYear = (int) date('Y') + 1;

        return [
            /**
             * Placa do veículo (padrão antigo ABC1234 ou Mercosul ABC1D23).
             *
             * @example ABC1D23
             */
            'license_plate' => ['required', 'string', 'max:10'],
            /**
             * Marca do veículo.
             *
             * @example Toyota
             */
            'brand' => ['required', 'string', 'max:100'],
            /**
             * Modelo do veículo.
             *
             * @example Corolla
             */
            'model' => ['required', 'string', 'max:100'],
            /**
             * Ano de fabricação/modelo do veículo.
             *
             * @example 2024
             */
            'year' => ['required', 'integer', 'min:1900', "max:{$maxYear}"],
            /**
             * Identificador UUID do cliente proprietário (opcional).
             *
             * @example 0195f3a0-1234-7000-8000-000000000001
             */
            'customer_id' => ['nullable', 'uuid'],
            /**
             * Cor do veículo (opcional).
             *
             * @example Prata
             */
            'color' => ['nullable', 'string', 'max:50'],
            /**
             * Quilometragem inicial do veículo em km (opcional).
             *
             * @example 15000
             */
            'mileage' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'license_plate' => 'placa',
            'brand' => 'marca',
            'model' => 'modelo',
            'year' => 'ano',
            'customer_id' => 'identificador do cliente',
            'color' => 'cor',
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
            'string' => 'O campo :attribute deve ser um texto.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'uuid' => 'O campo :attribute deve ser um UUID válido.',
            'max.string' => 'O campo :attribute não pode ter mais do que :max caracteres.',
            'max.numeric' => 'O campo :attribute não pode ser maior do que :max.',
            'min.numeric' => 'O campo :attribute deve ser no mínimo :min.',
        ];
    }
}
