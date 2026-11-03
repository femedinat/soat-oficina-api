<?php

declare(strict_types=1);

namespace Oficina\Customer\Application;

/** Dados de entrada para atualizar o contato (e-mail e telefone) de um cliente. */
final readonly class CustomerContactInput
{
    public function __construct(
        public string $email,
        public ?string $phone = null,
    ) {}
}
