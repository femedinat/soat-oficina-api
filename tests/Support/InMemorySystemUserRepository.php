<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Identity\Domain\Exception\CustomerAlreadyHasUserException;
use Oficina\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use Oficina\Identity\Domain\SystemUser;
use Oficina\Identity\Domain\SystemUserRepository;
use Oficina\Shared\Domain\ValueObject\Email;

final class InMemorySystemUserRepository implements SystemUserRepository
{
    /** @var array<int, SystemUser> */
    public array $items = [];

    public function findByEmail(Email $email): ?SystemUser
    {
        foreach ($this->items as $user) {
            if ($user->email->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function existsForCustomer(string $customerId): bool
    {
        foreach ($this->items as $user) {
            if ($user->customerId === $customerId) {
                return true;
            }
        }

        return false;
    }

    public function add(SystemUser $user): SystemUser
    {
        if ($this->findByEmail($user->email) !== null) {
            throw new EmailAlreadyRegisteredException;
        }

        if ($user->customerId !== null && $this->existsForCustomer($user->customerId)) {
            throw new CustomerAlreadyHasUserException;
        }

        $saved = $user->withId(count($this->items) + 1);
        $this->items[(int) $saved->id] = $saved;

        return $saved;
    }
}
