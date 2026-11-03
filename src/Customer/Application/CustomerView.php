<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\Customer;

/** Projeção de leitura do cliente para a camada de Interfaces. */
final readonly class CustomerView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $documentType,
        public string $document,
        public string $email,
        public ?string $phone,
        public bool $active = true,
    ) {}

    public static function fromEntity(Customer $customer): self
    {
        return new self(
            id: $customer->id->value,
            name: $customer->name(),
            documentType: $customer->document()->type->value,
            document: $customer->document()->value,
            email: $customer->email()->value,
            phone: $customer->phone()?->value,
            active: $customer->isActive(),
        );
    }
}
