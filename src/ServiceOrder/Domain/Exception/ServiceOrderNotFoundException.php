<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain\Exception;

use Oficina\Shared\Domain\Exception\NotFoundException;

final class ServiceOrderNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Ordem de serviço não encontrada.');
    }
}
