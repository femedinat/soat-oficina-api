<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;

/**
 * Executa uma alteração em um único item com a linha bloqueada (SELECT ... FOR UPDATE) do início ao commit.
 * Sem o bloqueio, uma entrada concorrente com uma reserva poderia sobrescrever o saldo (lost update).
 */
final readonly class LockedItemCommand
{
    public function __construct(
        private InventoryItemRepository $items,
        private TransactionManager $transaction,
        private EventPublisher $events,
    ) {}

    /** @param callable(InventoryItem): void $change */
    public function run(string $id, callable $change): InventoryItemView
    {
        $itemId = InventoryItemId::from($id);

        return $this->transaction->run(function () use ($itemId, $change): InventoryItemView {
            $item = $this->items->lockForUpdate([$itemId])[$itemId->value]
                ?? throw new InventoryItemNotFoundException;

            $change($item);

            $this->items->save($item);
            $this->events->publish(...$item->pullEvents());

            return InventoryItemView::fromEntity($item);
        });
    }
}
