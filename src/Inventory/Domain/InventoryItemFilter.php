<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

/** Critérios de busca do catálogo de peças e insumos. */
final readonly class InventoryItemFilter
{
    public function __construct(
        public ?string $search = null,
        public ?ItemType $type = null,
        public bool $includeInactive = false,
    ) {}
}
