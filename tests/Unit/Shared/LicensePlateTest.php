<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\LicensePlate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LicensePlateTest extends TestCase
{
    #[DataProvider('validPlates')]
    public function test_accepts_valid_plates(string $raw, string $expected, bool $mercosul): void
    {
        $plate = LicensePlate::from($raw);

        self::assertSame($expected, $plate->value);
        self::assertSame($mercosul, $plate->isMercosul());
    }

    /** @return array<string, array{string, string, bool}> */
    public static function validPlates(): array
    {
        return [
            'antiga com hífen' => ['abc-1234', 'ABC1234', false],
            'antiga' => ['ABC1234', 'ABC1234', false],
            'mercosul' => ['BRA2E19', 'BRA2E19', true],
            'mercosul com espaço' => ['bra 2e19', 'BRA2E19', true],
        ];
    }

    #[DataProvider('invalidPlates')]
    public function test_rejects_invalid_plates(string $raw): void
    {
        $this->expectException(InvalidValueException::class);
        LicensePlate::from($raw);
    }

    /** @return array<string, array{string}> */
    public static function invalidPlates(): array
    {
        return [
            'vazia' => [''],
            'curta' => ['AB1234'],
            'números no prefixo' => ['1BC1234'],
            'letra na posição errada' => ['ABC12D3'],
            'injeção' => ["ABC1234'; DROP TABLE vehicles;--"],
        ];
    }

    public function test_equality(): void
    {
        self::assertTrue(LicensePlate::from('abc-1234')->equals(LicensePlate::from('ABC1234')));
    }
}
