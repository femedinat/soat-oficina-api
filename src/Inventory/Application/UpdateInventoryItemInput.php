<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

/** Dados cadastrais editáveis (substituição completa). SKU é imutável; preço e saldo têm comandos próprios. */
final readonly class UpdateInventoryItemInput
{
    public function __construct(
        public string $name,
        public string $type,
        public string $unit,
        public string $minimumQuantity = '0',
        public ?string $description = null,
        public ?string $manufacturer = null,
    ) {}
}
