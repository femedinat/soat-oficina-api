<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_normalizes(): void
    {
        $email = Email::from('  Maria@Example.COM ');

        self::assertSame('maria@example.com', $email->value);
        self::assertTrue($email->equals(Email::from('maria@example.com')));
        self::assertFalse($email->equals(Email::from('outro@example.com')));
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid(string $raw): void
    {
        $this->expectException(InvalidValueException::class);

        Email::from($raw);
    }

    /** @return array<string, array{string}> */
    public static function invalid(): array
    {
        return [
            'vazio' => [''],
            'sem arroba' => ['maria.example.com'],
            'sem domínio' => ['maria@'],
            'longo' => [str_repeat('a', 250).'@b.com'],
        ];
    }
}
