<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Código interno da peça/insumo (SKU): único, maiúsculo, letras, números, "-", "_" e ".". */
final readonly class Sku
{
    private const string PATTERN = '/^[A-Z0-9][A-Z0-9._-]{1,39}$/';

    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $normalized = strtoupper(trim($raw));

        if (! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidValueException('SKU inválido: use de 2 a 40 caracteres (letras, números, ".", "-" ou "_").');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
