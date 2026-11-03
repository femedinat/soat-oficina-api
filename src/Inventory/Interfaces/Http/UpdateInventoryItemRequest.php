<?php

declare(strict_types=1);

namespace Oficina\Inventory\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Oficina\Inventory\Application\UpdateInventoryItemInput;

/** Dados cadastrais (substituição completa). SKU, preço e saldo não mudam por aqui. */
final class UpdateInventoryItemRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'string', 'in:PECA,INSUMO,peca,insumo'],
            /** Só pode mudar com o estoque zerado. */
            'unit' => ['required', 'string', 'in:UN,L,KG,M,un,l,kg,m'],
            'minimum_quantity' => ['sometimes', 'numeric', 'min:0', 'decimal:0,3'],
        ];
    }

    public function toInput(): UpdateInventoryItemInput
    {
        return new UpdateInventoryItemInput(
            name: (string) $this->string('name'),
            type: (string) $this->string('type'),
            unit: (string) $this->string('unit'),
            minimumQuantity: (string) $this->input('minimum_quantity', '0'),
            description: $this->filled('description') ? (string) $this->string('description') : null,
            manufacturer: $this->filled('manufacturer') ? (string) $this->string('manufacturer') : null,
        );
    }
}
