<?php

declare(strict_types=1);

namespace Oficina\Inventory\Interfaces\Http;

use Illuminate\Foundation\Http\FormRequest;
use Oficina\Inventory\Application\RegisterInventoryItemInput;

/**
 * Validação de formato do cadastro. SKU, unidade x fração e demais regras de negócio ficam no domínio.
 */
final class StoreInventoryItemRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** Código interno único (letras, números, ".", "-", "_"). @example FILTRO-OLEO-01 */
            'sku' => ['required', 'string', 'max:40'],
            /** @example Filtro de óleo */
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            /** @example Mann */
            'manufacturer' => ['nullable', 'string', 'max:120'],
            /** PECA ou INSUMO. @example PECA */
            'type' => ['required', 'string', 'in:PECA,INSUMO,peca,insumo'],
            /** UN (só inteiros), L, KG ou M. @example UN */
            'unit' => ['required', 'string', 'in:UN,L,KG,M,un,l,kg,m'],
            /** Preço de venda em reais, até 2 casas. @example 45.90 */
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            /** Estoque inicial, até 3 casas. @example 10 */
            'initial_quantity' => ['sometimes', 'numeric', 'min:0', 'decimal:0,3'],
            /** Estoque mínimo para alerta de reposição. @example 2 */
            'minimum_quantity' => ['sometimes', 'numeric', 'min:0', 'decimal:0,3'],
        ];
    }

    public function toInput(): RegisterInventoryItemInput
    {
        return new RegisterInventoryItemInput(
            sku: (string) $this->string('sku'),
            name: (string) $this->string('name'),
            type: (string) $this->string('type'),
            unit: (string) $this->string('unit'),
            price: (string) $this->input('price'),
            initialQuantity: (string) $this->input('initial_quantity', '0'),
            minimumQuantity: (string) $this->input('minimum_quantity', '0'),
            description: $this->filled('description') ? (string) $this->string('description') : null,
            manufacturer: $this->filled('manufacturer') ? (string) $this->string('manufacturer') : null,
        );
    }
}
