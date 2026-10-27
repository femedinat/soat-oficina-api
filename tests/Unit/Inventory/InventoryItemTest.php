<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use Oficina\Inventory\Domain\Event\InventoryItemDeactivated;
use Oficina\Inventory\Domain\Event\InventoryItemPriceChanged;
use Oficina\Inventory\Domain\Event\InventoryItemReactivated;
use Oficina\Inventory\Domain\Event\InventoryItemRegistered;
use Oficina\Inventory\Domain\Event\InventoryItemUpdated;
use Oficina\Inventory\Domain\Event\MinimumStockReached;
use Oficina\Inventory\Domain\Event\StockAdjusted;
use Oficina\Inventory\Domain\Event\StockReceived;
use Oficina\Inventory\Domain\Exception\InactiveInventoryItemException;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Inventory\Domain\MovementType;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\Sku;
use Oficina\Inventory\Domain\StockMovement;
use Oficina\Inventory\Domain\UnitOfMeasure;
use Oficina\Shared\Domain\Event\DomainEvent;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class InventoryItemTest extends TestCase
{
    private const string OS = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

    private static function item(string $initial = '10', string $minimum = '2', UnitOfMeasure $unit = UnitOfMeasure::UNIT): InventoryItem
    {
        return InventoryItem::register(
            Sku::from('PAST-FREIO-01'),
            '  Pastilha   de freio dianteira ',
            'Jogo com 4 peças',
            'Bosch',
            ItemType::PART,
            $unit,
            Money::fromDecimal('189.90'),
            Quantity::of($initial),
            Quantity::of($minimum),
        );
    }

    private static function os(): ServiceOrderReference
    {
        return ServiceOrderReference::from(self::OS);
    }

    /**
     * @param  list<DomainEvent>  $events
     * @return list<class-string>
     */
    private static function names(array $events): array
    {
        return array_map(static fn (DomainEvent $e): string => $e::class, $events);
    }

    public function test_registers_item_with_initial_stock_movement(): void
    {
        $item = self::item();

        self::assertSame('Pastilha de freio dianteira', $item->name());
        self::assertSame('Jogo com 4 peças', $item->description());
        self::assertSame('Bosch', $item->manufacturer());
        self::assertSame('PAST-FREIO-01', $item->sku()->value);
        self::assertSame(ItemType::PART, $item->type());
        self::assertSame(UnitOfMeasure::UNIT, $item->unit());
        self::assertSame(18990, $item->price()->cents);
        self::assertSame('10.000', $item->physicalQuantity()->toDecimal());
        self::assertSame('0.000', $item->reservedQuantity()->toDecimal());
        self::assertSame('10.000', $item->availableQuantity()->toDecimal());
        self::assertSame('2.000', $item->minimumQuantity()->toDecimal());
        self::assertTrue($item->isActive());
        self::assertFalse($item->isBelowMinimum());

        $events = $item->pullEvents();
        self::assertSame([InventoryItemRegistered::class], self::names($events));
        self::assertInstanceOf(InventoryItemRegistered::class, $events[0]);
        self::assertSame('PECA', $events[0]->type);
        self::assertSame([], $item->pullEvents(), 'eventos são entregues uma única vez');

        $movements = $item->pullNewMovements();
        self::assertCount(1, $movements);
        self::assertSame(MovementType::INITIAL_STOCK, $movements[0]->type);
        self::assertSame('10.000', $movements[0]->physicalAfter->toDecimal());
        self::assertSame([], $item->pullNewMovements());
    }

    public function test_registration_without_stock_has_no_movement_and_flags_restock(): void
    {
        $item = self::item('0', '0');

        self::assertSame([], $item->pullNewMovements());
        self::assertSame([InventoryItemRegistered::class, MinimumStockReached::class], self::names($item->pullEvents()));
        self::assertTrue($item->isBelowMinimum());
    }

    public function test_rejects_invalid_registration_data(): void
    {
        $cases = [
            'nome vazio' => fn () => InventoryItem::register(Sku::from('X1'), '  ', null, null, ItemType::PART, UnitOfMeasure::UNIT, Money::zero(), Quantity::zero(), Quantity::zero()),
            'nome longo' => fn () => InventoryItem::register(Sku::from('X1'), str_repeat('a', 121), null, null, ItemType::PART, UnitOfMeasure::UNIT, Money::zero(), Quantity::zero(), Quantity::zero()),
            'fração em UN' => fn () => InventoryItem::register(Sku::from('X1'), 'Filtro', null, null, ItemType::PART, UnitOfMeasure::UNIT, Money::zero(), Quantity::of('1.5'), Quantity::zero()),
            'mínimo fracionado em UN' => fn () => InventoryItem::register(Sku::from('X1'), 'Filtro', null, null, ItemType::PART, UnitOfMeasure::UNIT, Money::zero(), Quantity::zero(), Quantity::of('0.5')),
        ];

        foreach ($cases as $label => $case) {
            try {
                $case();
                self::fail("Deveria rejeitar: $label");
            } catch (InvalidValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_supply_accepts_fractional_quantities(): void
    {
        $oil = InventoryItem::register(Sku::from('OLEO-5W30'), 'Óleo 5W30', null, null, ItemType::SUPPLY, UnitOfMeasure::LITER, Money::fromDecimal('39.90'), Quantity::of('20.5'), Quantity::of('5'));

        $oil->reserve(Quantity::of('4.5'), self::os());

        self::assertSame('16.000', $oil->availableQuantity()->toDecimal());
    }

    public function test_revises_registration_data(): void
    {
        $item = self::item();
        $item->pullEvents();

        $item->revise('Pastilha traseira', null, '  ', ItemType::PART, UnitOfMeasure::UNIT, Quantity::of(3));

        self::assertSame('Pastilha traseira', $item->name());
        self::assertNull($item->description());
        self::assertNull($item->manufacturer());
        self::assertSame('3.000', $item->minimumQuantity()->toDecimal());
        self::assertSame([InventoryItemUpdated::class], self::names($item->pullEvents()));
    }

    public function test_raising_minimum_above_available_emits_restock_alert(): void
    {
        $item = self::item('10', '2');
        $item->pullEvents();

        $item->revise('Pastilha', null, null, ItemType::PART, UnitOfMeasure::UNIT, Quantity::of(10));

        self::assertSame([InventoryItemUpdated::class, MinimumStockReached::class], self::names($item->pullEvents()));
    }

    public function test_unit_changes_only_with_zero_stock(): void
    {
        $empty = self::item('0', '0');
        $empty->revise('Fluido', null, null, ItemType::SUPPLY, UnitOfMeasure::LITER, Quantity::of('0.5'));
        self::assertSame(UnitOfMeasure::LITER, $empty->unit());

        $this->expectException(DomainException::class);
        self::item()->revise('Pastilha', null, null, ItemType::PART, UnitOfMeasure::KILOGRAM, Quantity::zero());
    }

    public function test_changes_price_only_when_different(): void
    {
        $item = self::item();
        $item->pullEvents();

        $item->changePrice(Money::fromDecimal('189.90'));
        self::assertSame([], $item->pullEvents());

        $item->changePrice(Money::fromDecimal('199.90'));
        $events = $item->pullEvents();
        self::assertInstanceOf(InventoryItemPriceChanged::class, $events[0]);
        self::assertSame(18990, $events[0]->previousPriceCents);
        self::assertSame(19990, $events[0]->newPriceCents);
        self::assertSame(19990, $item->price()->cents);
    }

    public function test_receives_stock_with_invoice_reference(): void
    {
        $item = self::item();
        $item->pullNewMovements();
        $item->pullEvents();

        $item->receive(Quantity::of(5), 'NF-123', 'Compra fornecedor');

        self::assertSame('15.000', $item->physicalQuantity()->toDecimal());
        $movement = $item->pullNewMovements()[0];
        self::assertSame(MovementType::RECEIPT, $movement->type);
        self::assertSame('NF-123', $movement->reference);
        self::assertSame('Compra fornecedor', $movement->reason);
        $event = $item->pullEvents()[0];
        self::assertInstanceOf(StockReceived::class, $event);
        self::assertSame('15.000', $event->physicalAfter);
    }

    public function test_receive_rejects_zero_fraction_and_inactive_item(): void
    {
        $item = self::item();

        foreach ([Quantity::zero(), Quantity::of('0.5')] as $invalid) {
            try {
                $item->receive($invalid);
                self::fail('Entrada inválida deveria falhar.');
            } catch (InvalidValueException) {
            }
        }

        $item->deactivate();
        $this->expectException(InactiveInventoryItemException::class);
        $item->receive(Quantity::of(1));
    }

    public function test_adjusts_stock_both_ways_with_reason(): void
    {
        $item = self::item('10', '2');
        $item->pullNewMovements();
        $item->pullEvents();

        $item->adjust(Quantity::of(12), 'Contagem de inventário');
        $item->adjust(Quantity::of(1), '  Avaria  ');

        $movements = $item->pullNewMovements();
        self::assertSame([MovementType::ADJUSTMENT_IN, MovementType::ADJUSTMENT_OUT], array_map(static fn (StockMovement $m) => $m->type, $movements));
        self::assertSame('2.000', $movements[0]->quantity->toDecimal());
        self::assertSame('11.000', $movements[1]->quantity->toDecimal());
        self::assertSame('Avaria', $movements[1]->reason);
        self::assertSame([StockAdjusted::class, StockAdjusted::class, MinimumStockReached::class], self::names($item->pullEvents()));
    }

    public function test_adjust_rules(): void
    {
        $item = self::item();
        $item->reserve(Quantity::of(4), self::os());

        $cases = [
            'motivo vazio' => [fn () => $item->adjust(Quantity::of(5), ' '), InvalidValueException::class],
            'mesma quantidade' => [fn () => $item->adjust(Quantity::of(10), 'Contagem'), DomainException::class],
            'abaixo do reservado' => [fn () => $item->adjust(Quantity::of(3), 'Contagem'), DomainException::class],
            'fração em UN' => [fn () => $item->adjust(Quantity::of('5.5'), 'Contagem'), InvalidValueException::class],
        ];

        foreach ($cases as $label => [$case, $exception]) {
            try {
                $case();
                self::fail("Deveria rejeitar: $label");
            } catch (DomainException $e) {
                self::assertInstanceOf($exception, $e, $label);
            }
        }

        $item->adjust(Quantity::of(4), 'Contagem');
        self::assertSame('0.000', $item->availableQuantity()->toDecimal());
    }

    public function test_reserve_moves_from_available_to_reserved(): void
    {
        $item = self::item('10', '2');
        $item->pullNewMovements();
        $item->pullEvents();

        self::assertTrue($item->canReserve(Quantity::of(10)));
        self::assertFalse($item->canReserve(Quantity::of(11)));

        $item->reserve(Quantity::of(8), self::os());

        self::assertSame('10.000', $item->physicalQuantity()->toDecimal());
        self::assertSame('8.000', $item->reservedQuantity()->toDecimal());
        self::assertSame('2.000', $item->availableQuantity()->toDecimal());
        $movement = $item->pullNewMovements()[0];
        self::assertSame(MovementType::RESERVATION, $movement->type);
        self::assertSame(self::OS, $movement->reference);
        self::assertSame([MinimumStockReached::class], self::names($item->pullEvents()), 'disponível 2 <= mínimo 2');
    }

    public function test_reserve_rejects_more_than_available_without_changing_balance(): void
    {
        $item = self::item('3', '0');

        try {
            $item->reserve(Quantity::of(4), self::os());
            self::fail('Deveria faltar estoque.');
        } catch (InsufficientStockException $e) {
            self::assertSame([['item_id' => $item->id->value, 'sku' => 'PAST-FREIO-01', 'requested' => '4.000', 'available' => '3.000']], $e->shortages);
            self::assertStringContainsString('PAST-FREIO-01', $e->getMessage());
        }

        self::assertTrue($item->reservedQuantity()->isZero());
    }

    public function test_inactive_item_cannot_be_reserved(): void
    {
        $item = self::item();
        $item->deactivate();

        self::assertFalse($item->canReserve(Quantity::of(1)));
        $this->expectException(InactiveInventoryItemException::class);
        $item->reserve(Quantity::of(1), self::os());
    }

    public function test_consume_deducts_physical_and_reserved(): void
    {
        $item = self::item();
        $item->reserve(Quantity::of(4), self::os());
        $item->pullNewMovements();

        $item->consume(Quantity::of(4), self::os());

        self::assertSame('6.000', $item->physicalQuantity()->toDecimal());
        self::assertTrue($item->reservedQuantity()->isZero());
        self::assertSame(MovementType::CONSUMPTION, $item->pullNewMovements()[0]->type);
    }

    public function test_release_returns_reserved_to_available(): void
    {
        $item = self::item();
        $item->reserve(Quantity::of(4), self::os());
        $item->pullNewMovements();

        $item->releaseReservation(Quantity::of(4), self::os());

        self::assertSame('10.000', $item->availableQuantity()->toDecimal());
        self::assertSame(MovementType::RESERVATION_RELEASE, $item->pullNewMovements()[0]->type);
    }

    public function test_cannot_consume_or_release_more_than_reserved(): void
    {
        $item = self::item();
        $item->reserve(Quantity::of(2), self::os());

        try {
            $item->consume(Quantity::of(3), self::os());
            self::fail('Baixa acima do reservado deveria falhar.');
        } catch (DomainException) {
        }

        $this->expectException(DomainException::class);
        $item->releaseReservation(Quantity::of(3), self::os());
    }

    public function test_deactivation_rules(): void
    {
        $item = self::item();
        $item->pullEvents();
        $item->reserve(Quantity::of(1), self::os());

        try {
            $item->deactivate();
            self::fail('Não pode desativar com reserva.');
        } catch (DomainException) {
        }

        $item->releaseReservation(Quantity::of(1), self::os());
        $item->deactivate();
        $item->deactivate();
        self::assertFalse($item->isActive());

        $item->reactivate();
        $item->reactivate();
        self::assertTrue($item->isActive());

        $names = self::names($item->pullEvents());
        self::assertSame([InventoryItemDeactivated::class, InventoryItemReactivated::class], array_values(array_filter(
            $names,
            static fn (string $n): bool => in_array($n, [InventoryItemDeactivated::class, InventoryItemReactivated::class], true),
        )));
    }

    public function test_events_carry_occurrence_time(): void
    {
        foreach (self::item('0', '0')->pullEvents() as $event) {
            self::assertLessThanOrEqual(time(), $event->occurredAt()->getTimestamp());
        }
    }
}
