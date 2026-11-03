<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Identidade do agregado Cliente (UUID v4). */
final readonly class CustomerId
{
    private function __construct(public string $value) {}

    public static function generate(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return new self(sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    public static function from(string $value): self
    {
        $value = strtolower($value);

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value)) {
            throw new InvalidValueException('Identificador de cliente inválido.');
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
