<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Oficina\Identity\Application\AccessToken;
use Oficina\Identity\Application\AuthenticateSystemUser;
use Oficina\Identity\Application\TokenIssuer;
use Oficina\Identity\Domain\Exception\InvalidCredentialsException;
use Oficina\Identity\Domain\Role;
use Oficina\Identity\Domain\SystemUser;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\InMemorySystemUserRepository;

final class AuthenticateSystemUserTest extends TestCase
{
    private FakePasswordHasher $hasher;

    private AuthenticateSystemUser $useCase;

    protected function setUp(): void
    {
        $this->hasher = new FakePasswordHasher;

        $repository = new InMemorySystemUserRepository;
        $repository->add(SystemUser::register('Admin', Email::from('admin@oficina.local'), 'segredo-123', Role::ADMINISTRATOR, null, $this->hasher));

        $tokens = new class implements TokenIssuer
        {
            public function issueFor(SystemUser $user): AccessToken
            {
                return new AccessToken('token-'.$user->id, 3600);
            }
        };

        $this->useCase = new AuthenticateSystemUser($repository, $this->hasher, $tokens);
    }

    public function test_issues_token_for_valid_credentials(): void
    {
        $token = $this->useCase->execute(' Admin@Oficina.local ', 'segredo-123');

        self::assertSame('token-1', $token->token);
        self::assertSame('bearer', $token->type);
        self::assertSame(3600, $token->expiresInSeconds);
    }

    public function test_rejects_wrong_password(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->useCase->execute('admin@oficina.local', 'errada');
    }

    public function test_rejects_unknown_email_and_burns_hash_cost(): void
    {
        try {
            $this->useCase->execute('ninguem@oficina.local', 'segredo-123');
            self::fail('Deveria lançar InvalidCredentialsException.');
        } catch (InvalidCredentialsException) {
            self::assertSame(1, $this->hasher->burned);
        }
    }

    public function test_malformed_email_is_treated_as_invalid_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->useCase->execute('isto-nao-e-email', 'segredo-123');
    }

    public function test_user_requires_name(): void
    {
        $this->expectException(InvalidValueException::class);

        new SystemUser(1, '  ', Email::from('a@b.com'), 'x');
    }
}
