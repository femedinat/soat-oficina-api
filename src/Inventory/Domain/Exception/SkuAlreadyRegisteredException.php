<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Exception;

use Oficina\Shared\Domain\Exception\ConflictException;

final class SkuAlreadyRegisteredException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Já existe uma peça/insumo cadastrado com este SKU.');
    }
}
