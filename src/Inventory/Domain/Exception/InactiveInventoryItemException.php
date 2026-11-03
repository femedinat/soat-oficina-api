<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Exception;

use Oficina\Shared\Domain\Exception\DomainException;

final class InactiveInventoryItemException extends DomainException
{
    public function __construct(string $sku)
    {
        parent::__construct(sprintf('A peça/insumo %s está desativada e não pode ser movimentada.', $sku));
    }
}
