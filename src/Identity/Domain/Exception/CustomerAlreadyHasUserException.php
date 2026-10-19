<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain\Exception;

use Oficina\Shared\Domain\Exception\ConflictException;

final class CustomerAlreadyHasUserException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('O cliente informado já possui um usuário.');
    }
}
