<?php

declare(strict_types=1);

namespace Oficina\Identity\Infrastructure;

use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Identity\Domain\CustomerDirectory;

/** Consulta o contexto Clientes apenas por existência (anti-corruption layer mínima). */
final class EloquentCustomerDirectory implements CustomerDirectory
{
    public function exists(string $customerId): bool
    {
        return CustomerModel::query()->whereKey($customerId)->exists();
    }
}
