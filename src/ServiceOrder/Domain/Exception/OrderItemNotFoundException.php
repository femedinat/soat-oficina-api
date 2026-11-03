<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Exception;

use Oficina\Shared\Domain\Exception\NotFoundException;

final class OrderItemNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Item não encontrado nesta ordem de serviço.');
    }
}
