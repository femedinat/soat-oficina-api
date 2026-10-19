<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\ValueObject;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/** Endereço de e-mail normalizado (minúsculo, sem espaços nas pontas). */
final readonly class Email
{
    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $normalized = strtolower(trim($raw));

        if (strlen($normalized) > 254 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidValueException('E-mail inválido.');
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
