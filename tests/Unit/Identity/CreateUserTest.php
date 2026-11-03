<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Oficina\Identity\Application\CreateUser;
use Oficina\Identity\Application\CreateUserInput;
use Oficina\Identity\Domain\CustomerDirectory;
use Oficina\Identity\Domain\Exception\CustomerAlreadyHasUserException;
use Oficina\Identity\Domain\Exception\EmailAlreadyRegisteredException;
use Oficina\Identity\Domain\Role;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\InMemorySystemUserRepository;

final class CreateUserTest extends TestCase
{
    private const CUSTOMER_ID = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

    private InMemorySystemUserRepository $repository;

    private CreateUser $useCase;

    protected function setUp(): void
    {
        $this->repository = new InMemorySystemUserRepository;
        $customers = new class implements CustomerDirectory
        {
            public function exists(string $customerId): bool
            {
                return $customerId === '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';
            }
        };

        $this->useCase = new CreateUser($this->repository, $customers, new FakePasswordHasher);
    }

    private function input(Role $role = Role::MECHANIC, ?string $customerId = null, string $email = 'joao@oficina.local', string $password = 'senha-forte-1'): CreateUserInput
    {
        return new CreateUserInput('João', $email, $password, $role, $customerId);
    }

    public function test_creates_mechanic(): void
    {
        $view = $this->useCase->execute($this->input());

        self::assertSame(1, $view->id);
        self::assertSame('mechanic', $view->role);
        self::assertNull($view->customerId);
        self::assertSame('hash:senha-forte-1', $this->repository->items[1]->passwordHash());
    }

    public function test_creates_administrator(): void
    {
        self::assertSame('administrator', $this->useCase->execute($this->input(Role::ADMINISTRATOR))->role);
    }

    public function test_creates_customer_user_linked_to_existing_customer(): void
    {
        $view = $this->useCase->execute($this->input(Role::CUSTOMER, self::CUSTOMER_ID));

        self::assertSame('customer', $view->role);
        self::assertSame(self::CUSTOMER_ID, $view->customerId);
    }

    public function test_customer_role_requires_customer_id(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->useCase->execute($this->input(Role::CUSTOMER));
    }

    public function test_non_customer_role_rejects_customer_id(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->useCase->execute($this->input(Role::MECHANIC, self::CUSTOMER_ID));
    }

    public function test_rejects_unknown_customer(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->useCase->execute($this->input(Role::CUSTOMER, '00000000-0000-4000-8000-000000000000'));
    }

    public function test_customer_can_have_only_one_user(): void
    {
        $this->useCase->execute($this->input(Role::CUSTOMER, self::CUSTOMER_ID));

        $this->expectException(CustomerAlreadyHasUserException::class);
        $this->useCase->execute($this->input(Role::CUSTOMER, self::CUSTOMER_ID, 'outro@oficina.local'));
    }

    public function test_rejects_duplicate_email(): void
    {
        $this->useCase->execute($this->input());

        $this->expectException(EmailAlreadyRegisteredException::class);
        $this->useCase->execute($this->input(email: ' JOAO@oficina.local '));
    }

    public function test_rejects_short_password(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->useCase->execute($this->input(password: '1234567'));
    }

    public function test_rejects_invalid_email(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->useCase->execute($this->input(email: 'nao-e-email'));
    }

    public function test_role_labels_and_customer_requirement(): void
    {
        self::assertSame('Administrador', Role::ADMINISTRATOR->label());
        self::assertSame('Mecânico', Role::MECHANIC->label());
        self::assertSame('Cliente', Role::CUSTOMER->label());
        self::assertTrue(Role::CUSTOMER->requiresCustomer());
        self::assertFalse(Role::MECHANIC->requiresCustomer());
    }
}
