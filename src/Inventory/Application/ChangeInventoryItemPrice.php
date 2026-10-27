<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Shared\Domain\ValueObject\Money;

/** Caso de uso: alterar preço de venda (orçamentos já emitidos não mudam). */
final readonly class ChangeInventoryItemPrice
{
    public function __construct(private LockedItemCommand $command) {}

    public function execute(string $id, string $price): InventoryItemView
    {
        $money = Money::fromDecimal($price);

        return $this->command->run($id, static fn (InventoryItem $item) => $item->changePrice($money));
    }
}
