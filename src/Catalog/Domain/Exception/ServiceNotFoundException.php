<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Exception;

use Oficina\Shared\Domain\Exception\NotFoundException;

final class ServiceNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Serviço não encontrado.');
    }
}
