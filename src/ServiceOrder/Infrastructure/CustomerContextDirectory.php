<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Oficina\Customer\Application\CustomerView;
use Oficina\Customer\Application\FindCustomer;
use Oficina\Customer\Application\FindCustomerByDocument;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\ServiceOrder\Application\CustomerDirectory;
use Oficina\ServiceOrder\Application\CustomerSnapshot;
use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Camada anticorrupção: traduz o contexto Clientes para o que a OS precisa, via casos de uso públicos. */
final readonly class CustomerContextDirectory implements CustomerDirectory
{
    public function __construct(
        private FindCustomerByDocument $findByDocument,
        private FindCustomer $findById,
    ) {}

    public function findByDocument(string $document): CustomerSnapshot
    {
        return self::snapshot($this->findByDocument->execute($document));
    }

    public function findById(string $customerId): ?CustomerSnapshot
    {
        try {
            return self::snapshot($this->findById->execute($customerId));
        } catch (CustomerNotFoundException|InvalidValueException) {
            return null;
        }
    }

    private static function snapshot(CustomerView $view): CustomerSnapshot
    {
        return new CustomerSnapshot($view->id, $view->name, $view->document, $view->email, $view->active);
    }
}
