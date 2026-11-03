<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Shared\Domain\ValueObject\Document;

/** Caso de uso: consultar um cliente pelo documento (CPF ou CNPJ). */
final readonly class FindCustomerByDocument
{
    public function __construct(private CustomerRepository $customers) {}

    public function execute(string $document): CustomerView
    {
        $customer = $this->customers->findByDocument(Document::from($document))
            ?? throw new CustomerNotFoundException;

        return CustomerView::fromEntity($customer);
    }
}
