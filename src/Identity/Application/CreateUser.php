<?php

declare(strict_types=1);

namespace Oficina\Identity\Application;

use Oficina\Identity\Domain\CustomerDirectory;
use Oficina\Identity\Domain\Exception\CustomerAlreadyHasUserException;
use Oficina\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use Oficina\Identity\Domain\PasswordHasher;
use Oficina\Identity\Domain\SystemUser;
use Oficina\Identity\Domain\SystemUserRepository;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Email;

/** Caso de uso: criar usuário do sistema (Administrador, Mecânico ou Cliente). */
final readonly class CreateUser
{
    public function __construct(
        private SystemUserRepository $users,
        private CustomerDirectory $customers,
        private PasswordHasher $hasher,
    ) {}

    public function execute(CreateUserInput $input): UserView
    {
        $email = Email::from($input->email);
        $user = SystemUser::register(
            $input->name,
            $email,
            $input->password,
            $input->role,
            $input->customerId,
            $this->hasher,
        );

        if ($this->users->findByEmail($email) !== null) {
            throw new EmailAlreadyRegisteredException;
        }

        if ($user->customerId !== null) {
            if (! $this->customers->exists($user->customerId)) {
                throw new InvalidValueException('O cliente informado não existe.');
            }

            if ($this->users->existsForCustomer($user->customerId)) {
                throw new CustomerAlreadyHasUserException;
            }
        }

        return UserView::fromEntity($this->users->add($user));
    }
}
