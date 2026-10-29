<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Exception;

use Oficina\Shared\Domain\Exception\ConflictException;

final class ServiceAlreadyRegisteredException extends ConflictException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('Já existe um serviço com o código %s.', $code));
    }

    public static function withName(string $name): self
    {
        return new self(sprintf('Já existe um serviço com o nome "%s".', $name));
    }
}
