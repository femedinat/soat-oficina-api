<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

/** Dados de entrada para cadastrar/atualizar um cliente (valores crus, validados pelo domínio). */
final readonly class CustomerInput
{
    public function __construct(
        public string $name,
        public string $document,
        public string $email,
        public ?string $phone = null,
    ) {}
}
