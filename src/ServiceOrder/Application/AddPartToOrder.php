<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\ServiceOrder\Domain\ServiceOrder;

/** Caso de uso (Fase 2): adicionar peça/insumo à OS (bloqueada se não houver estoque disponível). */
final readonly class AddPartToOrder
{
    public function __construct(
        private ServiceOrderCommand $command,
        private PartQuoter $quoter,
    ) {}

    public function execute(string $orderId, string $inventoryItemId, string $quantity): ServiceOrderView
    {
        [$line] = $this->quoter->quote([['inventory_item_id' => $inventoryItemId, 'quantity' => $quantity]]);

        return $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->addPart($line, $now));
    }
}
