<?php

declare(strict_types=1);

namespace Oficina\Identity\Infrastructure;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Oficina\Identity\Domain\Exception\CustomerAlreadyHasUserException;
use Oficina\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use Oficina\Identity\Domain\SystemUser;
use Oficina\Identity\Domain\SystemUserRepository;
use Oficina\Shared\Domain\ValueObject\Email;

/** Persistência do agregado SystemUser sobre a tabela `users`. */
final class EloquentSystemUserRepository implements SystemUserRepository
{
    public function findByEmail(Email $email): ?SystemUser
    {
        $user = User::query()->where('email', $email->value)->first();

        if ($user === null) {
            return null;
        }

        return new SystemUser(
            id: (int) $user->getKey(),
            name: $user->name,
            email: Email::from($user->email),
            passwordHash: $user->password,
            role: $user->role,
            customerId: $user->customer_id,
        );
    }

    public function existsForCustomer(string $customerId): bool
    {
        return User::query()->where('customer_id', $customerId)->exists();
    }

    public function add(SystemUser $user): SystemUser
    {
        $model = new User;
        $model->name = $user->name;
        $model->email = $user->email->value;
        $model->password = $user->passwordHash();
        $model->role = $user->role;
        $model->customer_id = $user->customerId;

        try {
            $model->save();
        } catch (UniqueConstraintViolationException $e) {
            // Corrida com a checagem do caso de uso: os índices únicos são a última barreira.
            throw str_contains($e->getMessage(), 'customer_id')
                ? new CustomerAlreadyHasUserException
                : new EmailAlreadyRegisteredException;
        }

        return $user->withId((int) $model->getKey());
    }
}
