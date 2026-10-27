<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Shared\Application\Page;

/** Caso de uso: listar clientes paginados. */
final readonly class ListCustomers
{
    public const MAX_PER_PAGE = 100;

    public function __construct(private CustomerRepository $customers) {}

    /** @return Page<CustomerView> */
    public function execute(int $page = 1, int $perPage = 15, ?bool $onlyActive = null): Page
    {
        $result = $this->customers->paginate(
            max(1, $page),
            min(max(1, $perPage), self::MAX_PER_PAGE),
            $onlyActive,
        );

        return new Page(
            array_map(
                static fn (Customer $customer): CustomerView => CustomerView::fromEntity($customer),
                $result->items,
            ),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }
}
