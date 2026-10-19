<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain;

/** Porta para consultar o contexto Clientes sem depender do seu domínio. */
interface CustomerDirectory
{
    public function exists(string $customerId): bool;
}
