<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_parses_decimal_amounts(string $raw, int $cents, string $decimal): void
    {
        $money = Money::fromDecimal($raw);

        self::assertSame($cents, $money->cents);
        self::assertSame($decimal, $money->toDecimal());
    }

    /** @return array<string, array{string, int, string}> */
    public static function validAmounts(): array
    {
        return [
            'inteiro' => ['150', 15000, '150.00'],
            'uma casa' => ['150.9', 15090, '150.90'],
            'duas casas' => ['0.05', 5, '0.05'],
            'vírgula' => [' 89,99 ', 8999, '89.99'],
            'zero' => ['0', 0, '0.00'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_amounts(string $raw): void
    {
        $this->expectException(InvalidValueException::class);
        Money::fromDecimal($raw);
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'negativo' => ['-1'],
            'três casas' => ['1.999'],
            'texto' => ['abc'],
            'vazio' => [''],
            'milhar' => ['1.000,00'],
        ];
    }

    public function test_rejects_negative_cents(): void
    {
        $this->expectException(InvalidValueException::class);
        Money::fromCents(-1);
    }

    public function test_adds_and_multiplies_by_fractional_quantity(): void
    {
        $liter = Money::fromCents(3990);

        self::assertSame(17955, $liter->multiplyByThousandths(4500)->cents);
        self::assertSame(2, Money::fromCents(3)->multiplyByThousandths(500)->cents, 'meio centavo arredonda para cima');
        self::assertTrue(Money::zero()->add($liter)->equals($liter));
        self::assertFalse($liter->equals(Money::zero()));
    }

    public function test_rejects_negative_multiplier(): void
    {
        $this->expectException(InvalidValueException::class);
        Money::fromCents(100)->multiplyByThousandths(-1);
    }
}
