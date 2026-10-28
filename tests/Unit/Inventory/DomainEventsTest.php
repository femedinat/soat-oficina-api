<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use DateTimeImmutable;
use Oficina\Inventory\Domain\Event\InventoryItemDeactivated;
use Oficina\Inventory\Domain\Event\InventoryItemPriceChanged;
use Oficina\Inventory\Domain\Event\InventoryItemReactivated;
use Oficina\Inventory\Domain\Event\InventoryItemRegistered;
use Oficina\Inventory\Domain\Event\InventoryItemUpdated;
use Oficina\Inventory\Domain\Event\MinimumStockReached;
use Oficina\Inventory\Domain\Event\StockAdjusted;
use Oficina\Inventory\Domain\Event\StockConsumed;
use Oficina\Inventory\Domain\Event\StockReceived;
use Oficina\Inventory\Domain\Event\StockReservationRefused;
use Oficina\Inventory\Domain\Event\StockReservationReleased;
use Oficina\Inventory\Domain\Event\StockReserved;
use Oficina\Shared\Domain\Event\DomainEvent;
use PHPUnit\Framework\TestCase;

/** Os 11 eventos do Event Storming de Peças e Insumos (+ reativação) existem no código com a data do fato. */
final class DomainEventsTest extends TestCase
{
    public function test_every_event_records_when_it_happened(): void
    {
        $at = new DateTimeImmutable('2026-10-19 10:00:00');
        $id = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';
        $lines = [['item_id' => $id, 'quantity' => '1.000']];

        $events = [
            new InventoryItemRegistered($id, 'SKU', 'Nome', 'PECA', $at),
            new InventoryItemUpdated($id, $at),
            new InventoryItemPriceChanged($id, 100, 200, $at),
            new InventoryItemDeactivated($id, $at),
            new InventoryItemReactivated($id, $at),
            new StockReceived($id, '1.000', '2.000', 'NF-1', $at),
            new StockAdjusted($id, '2.000', '1.000', 'Contagem', $at),
            new StockReserved($id, $lines, $at),
            new StockReservationRefused($id, [['item_id' => $id, 'requested' => '2.000', 'available' => '1.000']], $at),
            new StockReservationReleased($id, $lines, $at),
            new StockConsumed($id, $lines, $at),
            new MinimumStockReached($id, 'SKU', '1.000', '2.000', $at),
        ];

        foreach ($events as $event) {
            self::assertInstanceOf(DomainEvent::class, $event);
            self::assertSame($at, $event->occurredAt());
        }
    }
}
