<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Document;
use Oficina\Shared\Domain\ValueObject\Email;

/**
 * Agregado raiz do contexto Clientes: pessoa física (CPF) ou jurídica (CNPJ) atendida pela oficina.
 */
final class Customer
{
    public const NAME_MAX_LENGTH = 150;

    private function __construct(
        public readonly CustomerId $id,
        private string $name,
        private Document $document,
        private Email $email,
        private ?Phone $phone,
    ) {
        $this->name = self::guardName($name);
    }

    public static function register(string $name, Document $document, Email $email, ?Phone $phone = null): self
    {
        return new self(CustomerId::generate(), $name, $document, $email, $phone);
    }

    /** Reconstitui o agregado a partir da persistência (sem gerar nova identidade). */
    public static function reconstitute(CustomerId $id, string $name, Document $document, Email $email, ?Phone $phone): self
    {
        return new self($id, $name, $document, $email, $phone);
    }

    public function revise(string $name, Document $document, Email $email, ?Phone $phone): void
    {
        $this->name = self::guardName($name);
        $this->document = $document;
        $this->email = $email;
        $this->phone = $phone;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function document(): Document
    {
        return $this->document;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function phone(): ?Phone
    {
        return $this->phone;
    }

    private static function guardName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));

        if ($name === '' || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidValueException(
                sprintf('O nome do cliente é obrigatório e deve ter até %d caracteres.', self::NAME_MAX_LENGTH),
            );
        }

        return $name;
    }
}
