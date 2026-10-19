<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain\Exception;

use Oficina\Shared\Domain\Exception\ConflictException;

final class EmailAlreadyRegisteredException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Já existe um usuário cadastrado com este e-mail.');
    }
}
