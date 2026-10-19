<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Oficina\Customer\Application\CustomerView;

/**
 * @property CustomerView $resource
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, document_type: string, document: string, email: string, phone: string|null}
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            /** @var 'CPF'|'CNPJ' */
            'document_type' => $this->resource->documentType,
            'document' => $this->resource->document,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
        ];
    }
}
