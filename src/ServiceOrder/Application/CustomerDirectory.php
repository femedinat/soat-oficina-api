<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\Shared\Domain\Exception\NotFoundException;

/** Porta para o contexto Clientes (camada anticorrupção na Infraestrutura). */
interface CustomerDirectory
{
    /** @throws NotFoundException */
    public function findByDocument(string $document): CustomerSnapshot;

    public function findById(string $customerId): ?CustomerSnapshot;
}
