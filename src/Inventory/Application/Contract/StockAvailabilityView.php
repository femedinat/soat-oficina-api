<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application\Contract;

/**
 * Resposta da consulta de disponibilidade. Traz também nome e preço atual para a OS montar o orçamento
 * e congelar o preço no item dela (alteração de preço futura não muda orçamento emitido).
 */
final readonly class StockAvailabilityView
{
    public function __construct(
        public string $itemId,
        public string $sku,
        public string $name,
        public string $type,
        public string $unit,
        public int $unitPriceCents,
        public string $requested,
        public string $available,
        public bool $active,
        public bool $sufficient,
    ) {}
}
