<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain\Exception;

use Oficina\Shared\Domain\Exception\ConflictException;

final class DocumentAlreadyRegisteredException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Já existe um cliente cadastrado com este CPF/CNPJ.');
    }
}
