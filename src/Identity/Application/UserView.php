<?php

declare(strict_types=1);

namespace Oficina\Identity\Application;

use Oficina\Identity\Domain\SystemUser;

/** Projeção de leitura do usuário (nunca expõe a senha). */
final readonly class UserView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
        public ?string $customerId,
    ) {}

    public static function fromEntity(SystemUser $user): self
    {
        return new self((int) $user->id, $user->name, $user->email->value, $user->role->value, $user->customerId);
    }
}
