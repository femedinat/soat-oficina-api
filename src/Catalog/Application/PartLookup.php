<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Shared\Domain\Exception\NotFoundException;

/** Porta para o contexto Estoque: o Catálogo só precisa saber se a peça existe e está ativa. */
interface PartLookup
{
    /** @throws NotFoundException se a peça não existir */
    public function isActive(string $inventoryItemId): bool;
}
