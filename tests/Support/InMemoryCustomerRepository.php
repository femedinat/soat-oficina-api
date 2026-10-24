<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Document;

/** Repositório em memória para testes unitários dos casos de uso (sem banco). */
final class InMemoryCustomerRepository implements CustomerRepository
{
    /** @var array<string, Customer> */
    private array $items = [];

    public function save(Customer $customer): void
    {
        $this->items[$customer->id->value] = $customer;
    }

    public function findById(CustomerId $id): ?Customer
    {
        return $this->items[$id->value] ?? null;
    }

    public function findByDocument(Document $document): ?Customer
    {
        foreach ($this->items as $customer) {
            if ($customer->document()->equals($document)) {
                return $customer;
            }
        }

        return null;
    }

    public function delete(Customer $customer): void
    {
        unset($this->items[$customer->id->value]);
    }

    public function paginate(int $page, int $perPage): Page
    {
        $all = array_values($this->items);

        return new Page(array_slice($all, ($page - 1) * $perPage, $perPage), count($all), $page, $perPage);
    }
}
