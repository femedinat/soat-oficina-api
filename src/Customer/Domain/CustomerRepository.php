<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain;

use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Document;

interface CustomerRepository
{
    /**
     * Persiste (insere ou atualiza) o cliente.
     *
     * @throws Exception\DocumentAlreadyRegisteredException
     */
    public function save(Customer $customer): void;

    public function findById(CustomerId $id): ?Customer;

    public function findByDocument(Document $document): ?Customer;

    public function delete(Customer $customer): void;

    /** @return Page<Customer> */
    public function paginate(int $page, int $perPage, ?bool $onlyActive = null): Page;
}
