<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerContactNotifier;

/** Dublê de teste em memória para verificar o envio de confirmação de contato. */
final class FakeCustomerContactNotifier implements CustomerContactNotifier
{
    /** @var list<Customer> */
    public array $notifiedCustomers = [];

    public function sendContactUpdatedConfirmation(Customer $customer): void
    {
        $this->notifiedCustomers[] = $customer;
    }
}
