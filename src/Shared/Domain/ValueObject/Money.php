<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\ValueObject;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Valor monetário em reais, guardado em centavos (inteiro) para não sofrer erro de ponto flutuante.
 * Shared Kernel: usado por Estoque (preço de peça/insumo), Catálogo (preço de serviço) e OS (orçamento).
 */
final readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        if ($cents < 0) {
            throw new InvalidValueException('Valor monetário não pode ser negativo.');
        }

        return new self($cents);
    }

    /** Aceita "150", "150.9", "150.90" ou "150,90" (sem separador de milhar). */
    public static function fromDecimal(string $amount): self
    {
        $normalized = str_replace(',', '.', trim($amount));

        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidValueException('Valor monetário inválido: use até 2 casas decimais e não negativo.');
        }

        [$integer, $fraction] = array_pad(explode('.', $normalized), 2, '');

        return new self(((int) $integer * 100) + (int) str_pad($fraction, 2, '0'));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /** Multiplica por uma quantidade em milésimos (ex.: 4,5 L = 4500), arredondando meio centavo para cima. */
    public function multiplyByThousandths(int $thousandths): self
    {
        if ($thousandths < 0) {
            throw new InvalidValueException('Multiplicador não pode ser negativo.');
        }

        return new self(intdiv(($this->cents * $thousandths) + 500, 1000));
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
