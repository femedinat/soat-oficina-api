<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Oficina\ServiceOrder\Application\VehicleData;

/** Validação de formato da abertura da OS. Regras de negócio ficam no domínio/aplicação. */
final class OpenServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** CPF ou CNPJ do cliente, com ou sem máscara. @example 529.982.247-25 */
            'document' => ['required', 'string', 'max:20'],
            /** @example ABC1D23 */
            'vehicle.license_plate' => ['required', 'string', 'max:10'],
            /** Obrigatório só se a placa ainda não estiver cadastrada. @example Fiat */
            'vehicle.brand' => ['nullable', 'string', 'max:50'],
            /** @example Argo */
            'vehicle.model' => ['nullable', 'string', 'max:50'],
            /** @example 2022 */
            'vehicle.year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'vehicle.color' => ['nullable', 'string', 'max:30'],
            'vehicle.mileage' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function vehicleData(): VehicleData
    {
        /** @var array{license_plate: string, brand?: string|null, model?: string|null, year?: int|string|null, color?: string|null, mileage?: int|string|null} $vehicle */
        $vehicle = $this->validated('vehicle');

        return new VehicleData(
            licensePlate: (string) $vehicle['license_plate'],
            brand: isset($vehicle['brand']) ? (string) $vehicle['brand'] : null,
            model: isset($vehicle['model']) ? (string) $vehicle['model'] : null,
            year: isset($vehicle['year']) ? (int) $vehicle['year'] : null,
            color: isset($vehicle['color']) ? (string) $vehicle['color'] : null,
            mileage: isset($vehicle['mileage']) ? (int) $vehicle['mileage'] : null,
        );
    }
}
