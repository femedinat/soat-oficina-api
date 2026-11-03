<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;
use Oficina\Catalog\Domain\StandardPart;

/** Projeção de leitura do serviço para a camada de Interfaces. */
final readonly class ServiceView
{
    /** @param list<array{inventory_item_id: string, quantity: string}> $standardParts */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $category,
        public int $laborPriceCents,
        public string $laborPrice,
        public int $estimatedMinutes,
        public array $standardParts,
        public bool $active,
    ) {}

    public static function fromEntity(Service $service): self
    {
        return new self(
            id: $service->id->value,
            code: $service->code()->value,
            name: $service->name(),
            description: $service->description(),
            category: $service->category(),
            laborPriceCents: $service->laborPrice()->cents,
            laborPrice: $service->laborPrice()->toDecimal(),
            estimatedMinutes: $service->estimatedMinutes(),
            standardParts: array_map(
                static fn (StandardPart $part): array => ['inventory_item_id' => $part->inventoryItemId, 'quantity' => $part->quantity()],
                $service->standardParts(),
            ),
            active: $service->isActive(),
        );
    }
}
