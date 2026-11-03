<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application\Contract;

/**
 * O que a OS precisa de um serviço para orçá-lo: nome, preço e tempo estimado de agora.
 * A OS copia esses valores no item (preço congelado) e usa as peças padrão como sugestão.
 */
final readonly class ServiceQuote
{
    /** @param list<array{inventory_item_id: string, quantity: string}> $standardParts */
    public function __construct(
        public string $serviceId,
        public string $code,
        public string $name,
        public int $laborPriceCents,
        public int $estimatedMinutes,
        public array $standardParts,
    ) {}
}
