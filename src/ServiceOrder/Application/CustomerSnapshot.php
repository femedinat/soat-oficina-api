<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

/** O que a OS sabe de um cliente (cópia de leitura vinda do contexto Clientes). */
final readonly class CustomerSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public string $document,
        public string $email,
        public bool $active,
    ) {}
}
