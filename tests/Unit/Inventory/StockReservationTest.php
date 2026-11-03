<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use Oficina\Inventory\Domain\Event\StockConsumed;
use Oficina\Inventory\Domain\Event\StockReservationReleased;
use Oficina\Inventory\Domain\Event\StockReserved;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\ReservationLine;
use Oficina\Inventory\Domain\ReservationStatus;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\StockReservation;
use Oficina\Shared\Domain\Exception\DomainException;
use PHPUnit\Framework\TestCase;

final class StockReservationTest extends TestCase
{
    private static function reservation(): StockReservation
    {
        return StockReservation::open(ServiceOrderReference::from('2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11'));
    }

    public function test_additional_repair_adds_to_existing_lines(): void
    {
        $pad = InventoryItemId::generate();
        $oil = InventoryItemId::generate();
        $reservation = self::reservation();

        $reservation->add([new ReservationLine($pad, Quantity::of(2))]);
        $reservation->add([new ReservationLine($pad, Quantity::of(1)), new ReservationLine($oil, Quantity::of('4.5'))]);

        $lines = $reservation->lines();
        self::assertCount(2, $lines);
        self::assertSame('3.000', $lines[0]->quantity->toDecimal());
        self::assertSame(['item_id' => $oil->value, 'quantity' => '4.500'], $lines[1]->toArray());

        $events = $reservation->pullEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(StockReserved::class, $events[1]);
        self::assertCount(2, $events[1]->lines, 'o evento traz só o que foi reservado agora');
    }

    public function test_consume_and_release_are_final(): void
    {
        $consumed = self::reservation();
        $consumed->add([new ReservationLine(InventoryItemId::generate(), Quantity::of(1))]);
        $consumed->consume();
        self::assertSame(ReservationStatus::CONSUMED, $consumed->status());
        self::assertFalse($consumed->isActive());
        self::assertInstanceOf(StockConsumed::class, $consumed->pullEvents()[1]);

        $released = self::reservation();
        $released->add([new ReservationLine(InventoryItemId::generate(), Quantity::of(1))]);
        $released->release();
        self::assertSame(ReservationStatus::RELEASED, $released->status());
        self::assertInstanceOf(StockReservationReleased::class, $released->pullEvents()[1]);

        $this->expectException(DomainException::class);
        $consumed->release();
    }

    public function test_rejects_empty_lines(): void
    {
        $this->expectException(DomainException::class);
        self::reservation()->add([]);
    }

    public function test_reconstitutes_from_persistence(): void
    {
        $id = InventoryItemId::generate();
        $reservation = StockReservation::reconstitute(
            ServiceOrderReference::from('2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11'),
            ReservationStatus::ACTIVE,
            [new ReservationLine($id, Quantity::of(2))],
        );

        self::assertTrue($reservation->isActive());
        self::assertTrue($reservation->lines()[0]->itemId->equals($id));
        self::assertSame([], $reservation->pullEvents());
    }
}
