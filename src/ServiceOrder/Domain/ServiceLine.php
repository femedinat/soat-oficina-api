<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\ValueObject\Money;
use Oficina\Shared\Domain\ValueObject\Uuid;

/**
 * Entidade interna da OS: um serviço orçado. Nome, preço e tempo estimado são cópias do catálogo
 * no momento da inclusão (preço congelado: alteração futura no catálogo não muda este orçamento).
 */
final readonly class ServiceLine
{
    public function __construct(
        public string $id,
        public string $serviceId,
        public string $code,
        public string $name,
        public Money $price,
        public int $estimatedMinutes,
        public bool $additional = false,
        public bool $approved = false,
    ) {}

    public static function quote(string $serviceId, string $code, string $name, Money $price, int $estimatedMinutes, bool $additional = false): self
    {
        return new self(Uuid::v4(), $serviceId, $code, $name, $price, $estimatedMinutes, $additional);
    }

    public function approve(): self
    {
        return new self($this->id, $this->serviceId, $this->code, $this->name, $this->price, $this->estimatedMinutes, $this->additional, true);
    }
}
