<?php

declare(strict_types=1);

namespace Oficina\Identity\Application;

use Oficina\Identity\Domain\Role;

final readonly class CreateUserInput
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public Role $role,
        public ?string $customerId = null,
    ) {}
}
