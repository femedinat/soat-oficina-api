<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Exception\DocumentAlreadyRegisteredException;
use Oficina\Customer\Domain\Phone;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\Email;

/** Caso de uso: cadastrar cliente (CPF/CNPJ obrigatório e único). */
final readonly class RegisterCustomer
{
    public function __construct(private CustomerRepository $customers) {}

    public function execute(CustomerInput $input): CustomerView
    {
        $document = Document::from($input->document);

        if ($this->customers->findByDocument($document) !== null) {
            throw new DocumentAlreadyRegisteredException;
        }

        $customer = Customer::register(
            $input->name,
            $document,
            Email::from($input->email),
            $input->phone !== null && $input->phone !== '' ? Phone::from($input->phone) : null,
        );

        $this->customers->save($customer);

        return CustomerView::fromEntity($customer);
    }
}
