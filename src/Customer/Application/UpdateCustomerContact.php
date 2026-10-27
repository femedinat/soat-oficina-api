<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\CustomerContactNotifier;
use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Customer\Domain\Phone;
use Oficina\Shared\Domain\ValueObject\Email;

/**
 * Caso de uso: atualizar contato do cliente (telefone/email) e enviar confirmação por e-mail.
 */
final readonly class UpdateCustomerContact
{
    public function __construct(
        private CustomerRepository $customers,
        private CustomerContactNotifier $notifier,
    ) {}

    public function execute(string $id, CustomerContactInput $input): CustomerView
    {
        $customer = $this->customers->findById(CustomerId::from($id)) ?? throw new CustomerNotFoundException;

        $customer->updateContact(
            Email::from($input->email),
            $input->phone !== null && $input->phone !== '' ? Phone::from($input->phone) : null,
        );

        $this->customers->save($customer);
        $this->notifier->sendContactUpdatedConfirmation($customer);

        return CustomerView::fromEntity($customer);
    }
}
