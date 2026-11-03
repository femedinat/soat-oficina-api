<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Exception;

use Oficina\Shared\Domain\Exception\NotFoundException;

final class CustomerNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Cliente não encontrado.');
    }
}
