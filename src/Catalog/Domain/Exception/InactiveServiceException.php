<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain\Exception;

use Oficina\Shared\Domain\Exception\DomainException;

final class InactiveServiceException extends DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('O serviço %s está inativo e não pode ser incluído em novos orçamentos.', $code));
    }
}
