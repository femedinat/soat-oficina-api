<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerContactNotifier;
use Oficina\Shared\Domain\ValueObject\Email;

/** Dublê de teste em memória para verificar o envio de confirmação de contato. */
final class FakeCustomerContactNotifier implements CustomerContactNotifier
{
    /** @var list<Customer> */
    public array $notifiedCustomers = [];

    /** @var list<Email|null> */
    public array $previousEmails = [];

    public function sendContactUpdatedConfirmation(Customer $customer, ?Email $previousEmail = null): void
    {
        $this->notifiedCustomers[] = $customer;
        $this->previousEmails[] = $previousEmail;
    }
}
