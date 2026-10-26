<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Shared\Application\Page;
use Oficina\Shared\Domain\ValueObject\Document;

/** Caso de uso: listar clientes paginados com filtro opcional por documento. */
final readonly class ListCustomers
{
    public const MAX_PER_PAGE = 100;

    public function __construct(private CustomerRepository $customers) {}

    /** @return Page<CustomerView> */
    public function execute(int $page = 1, int $perPage = 15, ?string $document = null): Page
    {
        $limit = min(max(1, $perPage), self::MAX_PER_PAGE);

        if ($document !== null && trim($document) !== '') {
            $customer = $this->customers->findByDocument(Document::from($document));
            $items = $customer === null ? [] : [CustomerView::fromEntity($customer)];

            return new Page($items, count($items), 1, $limit);
        }

        $result = $this->customers->paginate(max(1, $page), $limit);

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
