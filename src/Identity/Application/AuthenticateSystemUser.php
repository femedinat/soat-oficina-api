<?php

declare(strict_types=1);

namespace Oficina\Identity\Application;

use Oficina\Identity\Domain\Exception\InvalidCredentialsException;
use Oficina\Identity\Domain\PasswordHasher;
use Oficina\Identity\Domain\SystemUserRepository;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Email;

/** Caso de uso: autenticar administrador e emitir token de acesso. */
final readonly class AuthenticateSystemUser
{
    public function __construct(
        private SystemUserRepository $users,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {}

    /** @throws InvalidCredentialsException */
    public function execute(string $email, string $password): AccessToken
    {
        try {
            $user = $this->users->findByEmail(Email::from($email));
        } catch (InvalidValueException) {
            $user = null;
        }

        if ($user === null) {
            $this->hasher->burn($password);

            throw new InvalidCredentialsException;
        }

        if (! $user->passwordMatches($password, $this->hasher)) {
            throw new InvalidCredentialsException;
        }

        return $this->tokens->issueFor($user);
    }
}
