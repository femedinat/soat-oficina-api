<?php

declare(strict_types=1);

namespace Tests\Unit\Customer;

use Oficina\Customer\Application\CustomerInput;
use Oficina\Customer\Application\DeleteCustomer;
use Oficina\Customer\Application\FindCustomer;
use Oficina\Customer\Application\ListCustomers;
use Oficina\Customer\Application\RegisterCustomer;
use Oficina\Customer\Application\UpdateCustomer;
use Oficina\Customer\Domain\Exception\CustomerNotFoundException;
use Oficina\Customer\Domain\Exception\DocumentAlreadyRegisteredException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryCustomerRepository;

final class CustomerUseCasesTest extends TestCase
{
    private InMemoryCustomerRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCustomerRepository;
    }

    private function input(string $document = '529.982.247-25', string $email = 'maria@example.com', ?string $phone = null): CustomerInput
    {
        return new CustomerInput('Maria da Silva', $document, $email, $phone);
    }

    public function test_registers_customer(): void
    {
        $view = (new RegisterCustomer($this->repository))->execute($this->input(phone: '(11) 98765-4321'));

        self::assertSame('CPF', $view->documentType);
        self::assertSame('52998224725', $view->document);
        self::assertSame('11987654321', $view->phone);
        self::assertSame($view->id, (new FindCustomer($this->repository))->execute($view->id)->id);
    }

    public function test_registers_company_with_cnpj_and_empty_phone(): void
    {
        $view = (new RegisterCustomer($this->repository))->execute($this->input('11.222.333/0001-81', phone: ''));

        self::assertSame('CNPJ', $view->documentType);
        self::assertNull($view->phone);
    }

    public function test_rejects_invalid_document(): void
    {
        $this->expectException(InvalidValueException::class);

        (new RegisterCustomer($this->repository))->execute($this->input('111.111.111-11'));
    }

    public function test_rejects_duplicate_document(): void
    {
        $register = new RegisterCustomer($this->repository);
        $register->execute($this->input());

        $this->expectException(DocumentAlreadyRegisteredException::class);
        $register->execute($this->input('52998224725', 'outro@example.com'));
    }

    public function test_updates_customer(): void
    {
        $created = (new RegisterCustomer($this->repository))->execute($this->input());

        $updated = (new UpdateCustomer($this->repository))->execute($created->id, $this->input('11222333000181', 'novo@example.com'));

        self::assertSame($created->id, $updated->id);
        self::assertSame('11222333000181', $updated->document);
        self::assertSame('novo@example.com', $updated->email);
    }

    public function test_update_keeps_own_document(): void
    {
        $created = (new RegisterCustomer($this->repository))->execute($this->input());

        $updated = (new UpdateCustomer($this->repository))->execute($created->id, $this->input(email: 'novo@example.com'));

        self::assertSame('novo@example.com', $updated->email);
    }

    public function test_update_rejects_document_of_another_customer(): void
    {
        $register = new RegisterCustomer($this->repository);
        $register->execute($this->input('11222333000181'));
        $other = $register->execute($this->input());

        $this->expectException(DocumentAlreadyRegisteredException::class);
        (new UpdateCustomer($this->repository))->execute($other->id, $this->input('11.222.333/0001-81'));
    }

    public function test_update_unknown_customer(): void
    {
        $this->expectException(CustomerNotFoundException::class);

        (new UpdateCustomer($this->repository))->execute('2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11', $this->input());
    }

    public function test_find_unknown_customer(): void
    {
        $this->expectException(CustomerNotFoundException::class);

        (new FindCustomer($this->repository))->execute('2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11');
    }

    public function test_invalid_id_is_rejected(): void
    {
        $this->expectException(InvalidValueException::class);

        (new FindCustomer($this->repository))->execute('abc');
    }

    public function test_deletes_customer(): void
    {
        $created = (new RegisterCustomer($this->repository))->execute($this->input());

        (new DeleteCustomer($this->repository))->execute($created->id);

        $this->expectException(CustomerNotFoundException::class);
        (new FindCustomer($this->repository))->execute($created->id);
    }

    public function test_delete_unknown_customer(): void
    {
        $this->expectException(CustomerNotFoundException::class);

        (new DeleteCustomer($this->repository))->execute('2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11');
    }

    public function test_lists_with_pagination_limits(): void
    {
        $register = new RegisterCustomer($this->repository);
        $register->execute($this->input('52998224725'));
        $register->execute($this->input('11222333000181'));
        $register->execute($this->input('11144477735'));

        $page = (new ListCustomers($this->repository))->execute(2, 2);

        self::assertCount(1, $page->items);
        self::assertSame(3, $page->total);
        self::assertSame(2, $page->lastPage());

        $clamped = (new ListCustomers($this->repository))->execute(0, 1000);
        self::assertSame(1, $clamped->page);
        self::assertSame(ListCustomers::MAX_PER_PAGE, $clamped->perPage);
    }

    public function test_lists_filtered_by_document(): void
    {
        $register = new RegisterCustomer($this->repository);
        $customer1 = $register->execute($this->input('52998224725'));
        $register->execute($this->input('11222333000181'));

        $listCustomers = new ListCustomers($this->repository);

        $found = $listCustomers->execute(1, 15, '529.982.247-25');
        self::assertCount(1, $found->items);
        self::assertSame(1, $found->total);
        self::assertSame($customer1->id, $found->items[0]->id);

        $notFound = $listCustomers->execute(1, 15, '11144477735');
        self::assertCount(0, $notFound->items);
        self::assertSame(0, $notFound->total);
    }

    public function test_lists_filtered_by_invalid_document_throws(): void
    {
        $this->expectException(InvalidValueException::class);

        (new ListCustomers($this->repository))->execute(1, 15, 'documento-invalido');
    }
}
