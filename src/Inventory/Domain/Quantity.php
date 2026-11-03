<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Quantidade de estoque não negativa, com até 3 casas decimais (ex.: 4,5 L de óleo).
 * Guardada em milésimos (inteiro) para que somas e subtrações sejam exatas.
 */
final readonly class Quantity
{
    public const int SCALE = 1000;

    private const int MAX_THOUSANDTHS = 999_999_999_999;

    private function __construct(public int $thousandths) {}

    public static function fromThousandths(int $thousandths): self
    {
        if ($thousandths < 0 || $thousandths > self::MAX_THOUSANDTHS) {
            throw new InvalidValueException('Quantidade deve ser maior ou igual a zero e menor que 1 bilhão.');
        }

        return new self($thousandths);
    }

    /** Aceita inteiro ou texto decimal com ponto ou vírgula: 3, "3", "4.5", "4,500". */
    public static function of(int|string $value): self
    {
        if (is_int($value)) {
            return self::fromThousandths($value * self::SCALE);
        }

        $normalized = str_replace(',', '.', trim($value));

        if (! preg_match('/^\d{1,9}(\.\d{1,3})?$/', $normalized)) {
            throw new InvalidValueException('Quantidade inválida: use um número não negativo com até 3 casas decimais.');
        }

        [$integer, $fraction] = array_pad(explode('.', $normalized), 2, '');

        return new self(((int) $integer * self::SCALE) + (int) str_pad($fraction, 3, '0'));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return self::fromThousandths($this->thousandths + $other->thousandths);
    }

    public function subtract(self $other): self
    {
        if ($other->thousandths > $this->thousandths) {
            throw new InvalidValueException('Quantidade resultante não pode ser negativa.');
        }

        return new self($this->thousandths - $other->thousandths);
    }

    public function isZero(): bool
    {
        return $this->thousandths === 0;
    }

    public function isWhole(): bool
    {
        return $this->thousandths % self::SCALE === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->thousandths > $other->thousandths;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->thousandths <= $other->thousandths;
    }

    public function equals(self $other): bool
    {
        return $this->thousandths === $other->thousandths;
    }

    /** Formato decimal canônico com 3 casas, igual ao armazenado no banco: "4.500". */
    public function toDecimal(): string
    {
        return sprintf('%d.%03d', intdiv($this->thousandths, self::SCALE), $this->thousandths % self::SCALE);
    }
}
