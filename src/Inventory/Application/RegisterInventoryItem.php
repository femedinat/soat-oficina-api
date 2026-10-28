<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Domain\Exception\SkuAlreadyRegisteredException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\Sku;
use Oficina\Inventory\Domain\UnitOfMeasure;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;
use Oficina\Shared\Domain\ValueObject\Money;

/** Caso de uso: cadastrar peça/insumo (SKU único; estoque inicial vira movimentação no extrato). */
final readonly class RegisterInventoryItem
{
    public function __construct(
        private InventoryItemRepository $items,
        private TransactionManager $transaction,
        private EventPublisher $events,
    ) {}

    public function execute(RegisterInventoryItemInput $input): InventoryItemView
    {
        $item = InventoryItem::register(
            Sku::from($input->sku),
            $input->name,
            $input->description,
            $input->manufacturer,
            ItemType::parse($input->type),
            UnitOfMeasure::parse($input->unit),
            Money::fromDecimal($input->price),
            Quantity::of($input->initialQuantity),
            Quantity::of($input->minimumQuantity),
        );

        $this->transaction->run(function () use ($item): void {
            if ($this->items->findBySku($item->sku()) !== null) {
                throw new SkuAlreadyRegisteredException;
            }

            $this->items->save($item);
            $this->events->publish(...$item->pullEvents());
        });

        return InventoryItemView::fromEntity($item);
    }
}
