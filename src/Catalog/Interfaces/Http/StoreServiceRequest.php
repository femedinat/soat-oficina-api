<?php

declare(strict_types=1);

namespace Oficina\Catalog\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Oficina\Catalog\Application\RegisterServiceInput;
use Oficina\Catalog\Domain\Service;

final class StoreServiceRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** Código único e imutável (letras, números, ".", "-", "_"). @example TROCA-OLEO */
            'code' => ['required', 'string', 'max:30'],
            /** @example Troca de óleo */
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            /** @example Manutenção preventiva */
            'category' => ['nullable', 'string', 'max:60'],
            /** Preço da mão de obra em reais, até 2 casas. @example 120.00 */
            'labor_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            /** Tempo estimado de execução em minutos. @example 60 */
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:'.Service::MAX_ESTIMATED_MINUTES],
        ];
    }

    public function toInput(): RegisterServiceInput
    {
        return new RegisterServiceInput(
            code: (string) $this->string('code'),
            name: (string) $this->string('name'),
            description: $this->filled('description') ? (string) $this->string('description') : null,
            category: $this->filled('category') ? (string) $this->string('category') : null,
            laborPrice: (string) $this->input('labor_price'),
            estimatedMinutes: $this->integer('estimated_minutes'),
        );
    }
}
