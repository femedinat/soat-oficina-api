<?php

declare(strict_types=1);

namespace Oficina\Identity\Domain;

use Oficina\Shared\Domain\ValueObject\Email;

interface SystemUserRepository
{
    public function findByEmail(Email $email): ?SystemUser;

    public function existsForCustomer(string $customerId): bool;

    /**
     * Persiste um novo usuário e devolve-o com o id gerado.
     *
     * @throws Exception\EmailAlreadyRegisteredException
     * @throws Exception\CustomerAlreadyHasUserException
     */
    public function add(SystemUser $user): SystemUser;
}
