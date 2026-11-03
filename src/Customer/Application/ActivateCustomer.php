<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;

/** Caso de uso: reativar um cliente inativo. */
final readonly class ActivateCustomer
{
    public function __construct(private CustomerRepository $customers) {}

    public function execute(string $id): CustomerView
    {
        $customer = $this->customers->findById(CustomerId::from($id)) ?? throw new CustomerNotFoundException;

        $customer->activate();

        $this->customers->save($customer);

        return CustomerView::fromEntity($customer);
    }
}
