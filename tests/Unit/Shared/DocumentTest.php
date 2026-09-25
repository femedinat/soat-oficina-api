<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\DocumentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    #[DataProvider('validDocuments')]
    public function test_accepts_valid_documents(string $raw, string $expected, DocumentType $type): void
    {
        $doc = Document::from($raw);

        self::assertSame($expected, $doc->value);
        self::assertSame($type, $doc->type);
    }

    /** @return array<string, array{string, string, DocumentType}> */
    public static function validDocuments(): array
    {
        return [
            'cpf formatado' => ['529.982.247-25', '52998224725', DocumentType::CPF],
            'cpf sem máscara' => ['52998224725', '52998224725', DocumentType::CPF],
            'cnpj numérico' => ['11.222.333/0001-81', '11222333000181', DocumentType::CNPJ],
            'cnpj alfanumérico' => ['12.ABC.345/01DE-35', '12ABC34501DE35', DocumentType::CNPJ],
            'cnpj alfanumérico minúsculo' => ['12abc34501de35', '12ABC34501DE35', DocumentType::CNPJ],
        ];
    }

    #[DataProvider('invalidDocuments')]
    public function test_rejects_invalid_documents(string $raw): void
    {
        $this->expectException(InvalidValueException::class);
        Document::from($raw);
    }

    /** @return array<string, array{string}> */
    public static function invalidDocuments(): array
    {
        return [
            'vazio' => [''],
            'cpf dígito errado' => ['529.982.247-26'],
            'cpf repetido' => ['111.111.111-11'],
            'cnpj dígito errado' => ['11.222.333/0001-82'],
            'cnpj zerado' => ['00.000.000/0000-00'],
            'cnpj com letra no DV' => ['12ABC34501DE3A'],
            'tamanho errado' => ['1234567'],
        ];
    }

    public function test_formats_and_masks(): void
    {
        $cpf = Document::from('52998224725');
        $cnpj = Document::from('11222333000181');

        self::assertSame('529.982.247-25', $cpf->formatted());
        self::assertSame('***.982.***-**', $cpf->masked());
        self::assertSame('11.222.333/0001-81', $cnpj->formatted());
        self::assertSame('11.***.***/0001-**', $cnpj->masked());
    }

    public function test_equality_ignores_formatting(): void
    {
        self::assertTrue(Document::from('529.982.247-25')->equals(Document::from('52998224725')));
    }
}
