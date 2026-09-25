<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\ValueObject;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Placa de veículo brasileira: padrão antigo (ABC1234) ou Mercosul (ABC1D23).
 */
final readonly class LicensePlate
{
    private const string OLD_PATTERN = '/^[A-Z]{3}\d{4}$/';

    private const string MERCOSUL_PATTERN = '/^[A-Z]{3}\d[A-Z]\d{2}$/';

    private function __construct(public string $value) {}

    public static function from(string $raw): self
    {
        $normalized = strtoupper((string) preg_replace('/[\s-]/', '', $raw));

        if (! preg_match(self::OLD_PATTERN, $normalized) && ! preg_match(self::MERCOSUL_PATTERN, $normalized)) {
            throw new InvalidValueException('Placa de veículo inválida.');
        }

        return new self($normalized);
    }

    public function isMercosul(): bool
    {
        return (bool) preg_match(self::MERCOSUL_PATTERN, $this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
