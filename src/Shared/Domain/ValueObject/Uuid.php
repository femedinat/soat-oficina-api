<?php

declare(strict_types=1);

namespace Oficina\Shared\Domain\ValueObject;

/** Geração e validação de UUID v4 sem depender do framework. */
final class Uuid
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, $value);
    }
}
