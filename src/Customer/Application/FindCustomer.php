<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;

/** Caso de uso: consultar um cliente. */
final readonly class FindCustomer
{
    public function __construct(private CustomerRepository $customers) {}

    public function execute(string $id): CustomerView
    {
        $customer = $this->customers->findById(CustomerId::from($id)) ?? throw new CustomerNotFoundException;

        return CustomerView::fromEntity($customer);
    }
}
