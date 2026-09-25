<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\ValueObject;

enum DocumentType: string
{
    case CPF = 'CPF';
    case CNPJ = 'CNPJ';
}
