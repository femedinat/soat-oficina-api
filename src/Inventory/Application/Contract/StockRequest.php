<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application\Contract;

/** Uma linha pedida pela OS: qual peça/insumo e quanto (texto decimal, ex.: "2" ou "4.5"). */
final readonly class StockRequest
{
    public function __construct(
        public string $itemId,
        public string $quantity,
    ) {}
}
