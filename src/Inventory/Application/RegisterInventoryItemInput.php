<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

/** Dados para cadastrar peça/insumo (valores crus; o domínio valida). Quantidades e preço em texto decimal. */
final readonly class RegisterInventoryItemInput
{
    public function __construct(
        public string $sku,
        public string $name,
        public string $type,
        public string $unit,
        public string $price,
        public string $initialQuantity = '0',
        public string $minimumQuantity = '0',
        public ?string $description = null,
        public ?string $manufacturer = null,
    ) {}
}
