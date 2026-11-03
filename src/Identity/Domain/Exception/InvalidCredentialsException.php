<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain\Exception;

use Oficina\Shared\Domain\Exception\DomainException;

/** Credenciais inválidas. A camada de Interfaces traduz para HTTP 401. Mensagem genérica de propósito. */
final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Credenciais inválidas.');
    }
}
