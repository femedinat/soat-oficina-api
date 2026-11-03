<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Inventory\Domain\MovementType;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\Sku;
use Oficina\Inventory\Domain\UnitOfMeasure;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    #[DataProvider('validQuantities')]
    public function test_quantity_parses_integers_and_decimals(int|string $raw, int $thousandths, string $decimal): void
    {
        $quantity = Quantity::of($raw);

        self::assertSame($thousandths, $quantity->thousandths);
        self::assertSame($decimal, $quantity->toDecimal());
    }

    /** @return array<string, array{int|string, int, string}> */
    public static function validQuantities(): array
    {
        return [
            'int' => [3, 3000, '3.000'],
            'texto inteiro' => ['12', 12000, '12.000'],
            'meio litro' => ['4.5', 4500, '4.500'],
            'vírgula' => ['0,125', 125, '0.125'],
            'zero' => ['0', 0, '0.000'],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_quantity_rejects_invalid_values(int|string $raw): void
    {
        $this->expectException(InvalidValueException::class);
        Quantity::of($raw);
    }

    /** @return array<string, array{int|string}> */
    public static function invalidQuantities(): array
    {
        return [
            'negativa int' => [-1],
            'negativa texto' => ['-1'],
            'quatro casas' => ['1.0001'],
            'texto' => ['dois'],
            'vazio' => [''],
        ];
    }

    public function test_quantity_arithmetic_is_exact(): void
    {
        $a = Quantity::of('0.1');
        $b = Quantity::of('0.2');

        self::assertSame('0.300', $a->add($b)->toDecimal());
        self::assertSame('0.100', $b->subtract($a)->toDecimal());
        self::assertTrue($b->greaterThan($a));
        self::assertTrue($a->lessThanOrEqual($a));
        self::assertTrue($a->add($b)->equals(Quantity::of('0.3')));
        self::assertTrue(Quantity::zero()->isZero());
        self::assertTrue(Quantity::of(2)->isWhole());
        self::assertFalse(Quantity::of('2.5')->isWhole());
    }

    public function test_quantity_never_goes_negative_or_overflows(): void
    {
        try {
            Quantity::of(1)->subtract(Quantity::of(2));
            self::fail('Subtração negativa deveria falhar.');
        } catch (InvalidValueException) {
        }

        $this->expectException(InvalidValueException::class);
        Quantity::fromThousandths(1_000_000_000_000);
    }

    public function test_sku_normalizes_and_validates(): void
    {
        self::assertSame('FLT-OLEO-01', Sku::from(' flt-oleo-01 ')->value);
        self::assertTrue(Sku::from('ab.1')->equals(Sku::from('AB.1')));

        foreach (['A', '-ABC', 'com espaço', str_repeat('X', 41), 'ÓLEO'] as $invalid) {
            try {
                Sku::from($invalid);
                self::fail("SKU '$invalid' deveria ser inválido.");
            } catch (InvalidValueException) {
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_identifiers_validate_uuid(): void
    {
        $id = InventoryItemId::generate();

        self::assertTrue($id->equals(InventoryItemId::from(strtoupper($id->value))));
        self::assertTrue(ServiceOrderReference::from($id->value)->equals(ServiceOrderReference::from($id->value)));

        try {
            InventoryItemId::from('123');
            self::fail('Id inválido deveria falhar.');
        } catch (InvalidValueException) {
        }

        $this->expectException(InvalidValueException::class);
        ServiceOrderReference::from('os-1');
    }

    public function test_enums_parse_and_label(): void
    {
        self::assertSame(ItemType::SUPPLY, ItemType::parse(' insumo '));
        self::assertSame('Peça', ItemType::PART->label());
        self::assertSame('Insumo', ItemType::SUPPLY->label());
        self::assertSame(UnitOfMeasure::LITER, UnitOfMeasure::parse('l'));
        self::assertFalse(UnitOfMeasure::UNIT->allowsFraction());
        self::assertTrue(UnitOfMeasure::KILOGRAM->accepts(Quantity::of('0.5')));
        self::assertFalse(UnitOfMeasure::UNIT->accepts(Quantity::of('0.5')));

        foreach (UnitOfMeasure::cases() as $unit) {
            self::assertNotSame('', $unit->label());
        }
        foreach (MovementType::cases() as $type) {
            self::assertNotSame('', $type->label());
        }

        try {
            ItemType::parse('SERVICO');
            self::fail('Tipo inválido deveria falhar.');
        } catch (InvalidValueException) {
        }

        $this->expectException(InvalidValueException::class);
        UnitOfMeasure::parse('CX');
    }
}
