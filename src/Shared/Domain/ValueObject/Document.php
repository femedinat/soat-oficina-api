<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\ValueObject;

use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Documento de identificação do cliente: CPF (PF) ou CNPJ (PJ).
 *
 * Suporta o CNPJ alfanumérico (IN RFB nº 2.229/2024, vigente desde jul/2026):
 * 12 primeiros caracteres [A-Z0-9], 2 dígitos verificadores numéricos,
 * valor de cada caractere = ASCII - 48.
 */
final readonly class Document
{
    private function __construct(
        public string $value,
        public DocumentType $type,
    ) {}

    public static function from(string $raw): self
    {
        $normalized = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $raw));

        return match (true) {
            self::isValidCpf($normalized) => new self($normalized, DocumentType::CPF),
            self::isValidCnpj($normalized) => new self($normalized, DocumentType::CNPJ),
            default => throw new InvalidValueException('CPF/CNPJ inválido.'),
        };
    }

    public function formatted(): string
    {
        $v = $this->value;

        return $this->type === DocumentType::CPF
            ? sprintf('%s.%s.%s-%s', substr($v, 0, 3), substr($v, 3, 3), substr($v, 6, 3), substr($v, 9, 2))
            : sprintf('%s.%s.%s/%s-%s', substr($v, 0, 2), substr($v, 2, 3), substr($v, 5, 3), substr($v, 8, 4), substr($v, 12, 2));
    }

    /** Para logs/respostas públicas: nunca exponha o documento completo (LGPD). */
    public function masked(): string
    {
        return $this->type === DocumentType::CPF
            ? '***.'.substr($this->value, 3, 3).'.***-**'
            : substr($this->value, 0, 2).'.***.***/'.substr($this->value, 8, 4).'-**';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (! preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj) || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        $values = array_map(static fn (string $c): int => ord($c) - 48, str_split($cnpj));

        foreach ([12, 13] as $length) {
            $weight = $length - 7; // 5 para DV1, 6 para DV2
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += $values[$i] * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;
            if ($values[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
