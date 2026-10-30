<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Domain;

use Oficina\Shared\Domain\ValueObject\Money;

/** Orçamento calculado automaticamente: soma da mão de obra dos serviços e das peças/insumos. */
final readonly class Budget
{
    private function __construct(
        public Money $services,
        public Money $parts,
    ) {}

    /**
     * @param  list<ServiceLine>  $services
     * @param  list<PartLine>  $parts
     */
    public static function of(array $services, array $parts): self
    {
        $servicesTotal = Money::zero();
        foreach ($services as $line) {
            $servicesTotal = $servicesTotal->add($line->price);
        }

        $partsTotal = Money::zero();
        foreach ($parts as $line) {
            $partsTotal = $partsTotal->add($line->total());
        }

        return new self($servicesTotal, $partsTotal);
    }

    public function total(): Money
    {
        return $this->services->add($this->parts);
    }
}
