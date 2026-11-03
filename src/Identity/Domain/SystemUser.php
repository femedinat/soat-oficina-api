<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Email;

/**
 * Agregado raiz do contexto Identidade: usuário do sistema (Administrador, Mecânico ou Cliente).
 * Usuários com perfil Cliente são vinculados 1:1 a um Cliente (referência por id, sem acoplar contextos).
 */
final class SystemUser
{
    public const PASSWORD_MIN_LENGTH = 8;

    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly Email $email,
        private readonly string $passwordHash,
        public readonly Role $role = Role::ADMINISTRATOR,
        public readonly ?string $customerId = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidValueException('O nome do usuário é obrigatório.');
        }

        if ($role->requiresCustomer() && $customerId === null) {
            throw new InvalidValueException('Usuário com perfil Cliente deve estar vinculado a um cliente.');
        }

        if (! $role->requiresCustomer() && $customerId !== null) {
            throw new InvalidValueException('Somente usuários com perfil Cliente podem ser vinculados a um cliente.');
        }
    }

    /** Novo usuário (ainda sem id), com a senha já transformada em hash. */
    public static function register(
        string $name,
        Email $email,
        string $plainPassword,
        Role $role,
        ?string $customerId,
        PasswordHasher $hasher,
    ): self {
        if (mb_strlen($plainPassword) < self::PASSWORD_MIN_LENGTH) {
            throw new InvalidValueException(
                sprintf('A senha deve ter no mínimo %d caracteres.', self::PASSWORD_MIN_LENGTH),
            );
        }

        return new self(null, trim($name), $email, $hasher->hash($plainPassword), $role, $customerId);
    }

    public function withId(int $id): self
    {
        return new self($id, $this->name, $this->email, $this->passwordHash, $this->role, $this->customerId);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function passwordMatches(string $plainPassword, PasswordHasher $hasher): bool
    {
        return $hasher->verify($plainPassword, $this->passwordHash);
    }
}
