<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Quantidade de peça/insumo na OS, em milésimos (4,5 L = 4500), como no contexto Estoque. */
final readonly class Quantity
{
    private const int MAX_THOUSANDTHS = 999_999_999_999;

    private function __construct(public int $thousandths) {}

    public static function of(string $value): self
    {
        $normalized = str_replace(',', '.', trim($value));

        if (! preg_match('/^\d{1,12}(\.\d{1,3})?$/', $normalized)) {
            throw new InvalidValueException('Quantidade inválida: use até 3 casas decimais.');
        }

        [$integer, $fraction] = array_pad(explode('.', $normalized), 2, '');

        return self::fromThousandths(((int) $integer * 1000) + (int) str_pad($fraction, 3, '0'));
    }

    public static function fromThousandths(int $thousandths): self
    {
        if ($thousandths <= 0 || $thousandths > self::MAX_THOUSANDTHS) {
            throw new InvalidValueException('A quantidade deve ser maior que zero.');
        }

        return new self($thousandths);
    }

    public function add(self $other): self
    {
        return self::fromThousandths($this->thousandths + $other->thousandths);
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%03d', intdiv($this->thousandths, 1000), $this->thousandths % 1000);
    }
}
