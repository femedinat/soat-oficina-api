<?php

declare(strict_types=1);

namespace Tests\Unit\Customer;

use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerId;
use Oficina\Customer\Domain\Phone;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerTest extends TestCase
{
    public function test_registers_customer_with_normalized_data(): void
    {
        $customer = Customer::register('  Maria   da  Silva ', Document::from('529.982.247-25'), Email::from(' MARIA@Example.com '), Phone::from('(11) 98765-4321'));

        self::assertSame('Maria da Silva', $customer->name());
        self::assertSame('52998224725', $customer->document()->value);
        self::assertSame('maria@example.com', $customer->email()->value);
        self::assertSame('11987654321', $customer->phone()?->value);
        self::assertTrue($customer->isActive());
        self::assertNotEmpty($customer->id->value);
    }

    public function test_phone_is_optional(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'));

        self::assertNull($customer->phone());
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_name(string $name): void
    {
        $this->expectException(InvalidValueException::class);

        Customer::register($name, Document::from('52998224725'), Email::from('m@example.com'));
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return ['vazio' => [''], 'só espaços' => ['   '], 'longo demais' => [str_repeat('a', 151)]];
    }

    public function test_revise_replaces_data_and_keeps_identity(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'));
        $id = $customer->id;

        $customer->revise('Empresa X', Document::from('11.222.333/0001-81'), Email::from('x@example.com'), null);

        self::assertTrue($id->equals($customer->id));
        self::assertSame('Empresa X', $customer->name());
        self::assertSame('11222333000181', $customer->document()->value);
        self::assertSame('x@example.com', $customer->email()->value);
    }

    public function test_revise_rejects_invalid_name(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'));

        $this->expectException(InvalidValueException::class);
        $customer->revise('', Document::from('52998224725'), Email::from('m@example.com'), null);
    }

    public function test_customer_id_round_trip_and_validation(): void
    {
        $generated = CustomerId::generate();

        self::assertTrue($generated->equals(CustomerId::from(strtoupper($generated->value))));

        $this->expectException(InvalidValueException::class);
        CustomerId::from('not-a-uuid');
    }

    #[DataProvider('invalidPhones')]
    public function test_rejects_invalid_phone(string $raw): void
    {
        $this->expectException(InvalidValueException::class);

        Phone::from($raw);
    }

    /** @return array<string, array{string}> */
    public static function invalidPhones(): array
    {
        return ['curto' => ['1234'], 'sem ddd' => ['987654321'], 'letras' => ['abc'], 'longo' => ['119876543210']];
    }

    public function test_accepts_landline_and_mobile(): void
    {
        self::assertSame('1132345678', Phone::from('(11) 3234-5678')->value);
        self::assertSame('11987654321', Phone::from('11 98765-4321')->value);
    }

    public function test_updates_contact_keeping_name_and_document(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'), Phone::from('1132345678'));

        $customer->updateContact(Email::from('novo@example.com'), Phone::from('11987654321'));

        self::assertSame('Maria', $customer->name());
        self::assertSame('52998224725', $customer->document()->value);
        self::assertSame('novo@example.com', $customer->email()->value);
        self::assertSame('11987654321', $customer->phone()?->value);
    }

    public function test_deactivates_and_reactivates_customer(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'));

        $customer->deactivate();
        self::assertFalse($customer->isActive());

        $customer->activate();
        self::assertTrue($customer->isActive());
    }

    public function test_rejects_revise_when_customer_is_inactive(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'));
        $customer->deactivate();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Não é possível atualizar os dados de um cliente inativo.');

        $customer->revise('Maria Nova', Document::from('52998224725'), Email::from('m@example.com'), null);
    }

    public function test_rejects_update_contact_when_customer_is_inactive(): void
    {
        $customer = Customer::register('Maria', Document::from('52998224725'), Email::from('m@example.com'));
        $customer->deactivate();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Não é possível atualizar o contato de um cliente inativo.');

        $customer->updateContact(Email::from('novo@example.com'), null);
    }
}
