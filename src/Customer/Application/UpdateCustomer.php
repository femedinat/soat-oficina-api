<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Customer\Domain\Exception\DocumentAlreadyRegisteredException;
use Oficina\Customer\Domain\Phone;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\Email;

/** Caso de uso: atualizar dados do cliente (substituição completa). */
final readonly class UpdateCustomer
{
    public function __construct(private CustomerRepository $customers) {}

    public function execute(string $id, CustomerInput $input): CustomerView
    {
        $customer = $this->customers->findById(CustomerId::from($id)) ?? throw new CustomerNotFoundException;

        $document = Document::from($input->document);
        $owner = $this->customers->findByDocument($document);

        if ($owner !== null && ! $owner->id->equals($customer->id)) {
            throw new DocumentAlreadyRegisteredException;
        }

        $customer->revise(
            $input->name,
            $document,
            Email::from($input->email),
            $input->phone !== null && $input->phone !== '' ? Phone::from($input->phone) : null,
        );

        $this->customers->save($customer);

        return CustomerView::fromEntity($customer);
    }
}
