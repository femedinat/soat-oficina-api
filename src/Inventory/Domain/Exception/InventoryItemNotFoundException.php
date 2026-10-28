<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Exception;

use Oficina\Shared\Domain\Exception\NotFoundException;

final class InventoryItemNotFoundException extends NotFoundException
{
    public function __construct(string $id = '')
    {
        parent::__construct($id === '' ? 'Peça/insumo não encontrado.' : sprintf('Peça/insumo não encontrado: %s.', $id));
    }
}
