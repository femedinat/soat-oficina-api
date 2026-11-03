<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use Oficina\Inventory\Application\Contract\StockRequest;
use Oficina\Inventory\Application\InventoryServiceOrderStock;
use Oficina\Inventory\Application\RegisterInventoryItem;
use Oficina\Inventory\Application\RegisterInventoryItemInput;
use Oficina\Inventory\Domain\Event\StockConsumed;
use Oficina\Inventory\Domain\Event\StockReservationRefused;
use Oficina\Inventory\Domain\Event\StockReservationReleased;
use Oficina\Inventory\Domain\Event\StockReserved;
use Oficina\Inventory\Domain\Exception\InactiveInventoryItemException;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\ReservationStatus;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImmediateTransactionManager;
use Tests\Support\InMemoryInventoryItemRepository;
use Tests\Support\InMemoryStockReservationRepository;
use Tests\Support\SpyEventPublisher;

final class ServiceOrderStockTest extends TestCase
{
    private const string OS = '7d0f3a9e-1c2b-4d5e-8f60-123456789abc';

    private InMemoryInventoryItemRepository $items;

    private InMemoryStockReservationRepository $reservations;

    private SpyEventPublisher $events;

    private InventoryServiceOrderStock $stock;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItemRepository;
        $this->reservations = new InMemoryStockReservationRepository;
        $this->events = new SpyEventPublisher;
        $this->stock = new InventoryServiceOrderStock($this->items, $this->reservations, new ImmediateTransactionManager, $this->events);
    }

    private function item(string $sku, string $quantity, string $unit = 'UN'): string
    {
        return (new RegisterInventoryItem($this->items, new ImmediateTransactionManager, $this->events))
            ->execute(new RegisterInventoryItemInput($sku, $sku, $unit === 'UN' ? 'PECA' : 'INSUMO', $unit, '10.00', $quantity))
            ->id;
    }

    private function available(string $id): string
    {
        return $this->items->findById(InventoryItemId::from($id))?->availableQuantity()->toDecimal() ?? 'n/a';
    }

    public function test_checks_availability_without_reserving(): void
    {
        $pad = $this->item('PASTILHA', '2');
        $oil = $this->item('OLEO', '3.5', 'L');

        $views = $this->stock->checkAvailability([
            new StockRequest($pad, '1'),
            new StockRequest($oil, '4'),
            new StockRequest($pad, '2'),
        ]);

        self::assertCount(2, $views, 'itens repetidos são somados');
        self::assertSame('3.000', $views[0]->requested);
        self::assertFalse($views[0]->sufficient);
        self::assertSame(1000, $views[0]->unitPriceCents);
        self::assertSame('OLEO', $views[1]->sku);
        self::assertSame('L', $views[1]->unit);
        self::assertSame('3.500', $views[1]->available);
        self::assertFalse($views[1]->sufficient);
        self::assertTrue($views[1]->active);
        self::assertSame('2.000', $this->available($pad));
    }

    public function test_reserves_all_items_atomically_and_in_id_order(): void
    {
        $a = $this->item('PASTILHA', '4');
        $b = $this->item('OLEO', '10', 'L');
        $this->events->published = [];

        $this->stock->reserve(self::OS, [new StockRequest($b, '4.5'), new StockRequest($a, '4')]);

        self::assertSame('0.000', $this->available($a));
        self::assertSame('5.500', $this->available($b));
        $sorted = [$a, $b];
        sort($sorted);
        self::assertSame($sorted, $this->items->lockedIds, 'bloqueio sempre em ordem de id (evita deadlock)');
        self::assertCount(1, $this->events->ofType(StockReserved::class));

        $reservation = $this->reservations->findForServiceOrder(ServiceOrderReference::from(self::OS));
        self::assertNotNull($reservation);
        self::assertCount(2, $reservation->lines());
    }

    public function test_insufficient_stock_reserves_nothing_and_reports_every_shortage(): void
    {
        $a = $this->item('PASTILHA', '4');
        $b = $this->item('DISCO', '1');
        $c = $this->item('OLEO', '10', 'L');

        try {
            $this->stock->reserve(self::OS, [new StockRequest($a, '5'), new StockRequest($b, '2'), new StockRequest($c, '1')]);
            self::fail('Deveria faltar estoque.');
        } catch (InsufficientStockException $e) {
            self::assertCount(2, $e->shortages);
        }

        self::assertSame('4.000', $this->available($a));
        self::assertSame('10.000', $this->available($c), 'tudo ou nada: o item com saldo também não foi reservado');
        self::assertNull($this->reservations->findForServiceOrder(ServiceOrderReference::from(self::OS)));
        $refused = $this->events->ofType(StockReservationRefused::class);
        self::assertCount(1, $refused);
        self::assertSame(['item_id', 'requested', 'available'], array_keys($refused[0]->shortages[0]));
    }

    public function test_reserve_validates_input_and_items(): void
    {
        $active = $this->item('PASTILHA', '4');
        $cases = [
            'sem itens' => [fn () => $this->stock->reserve(self::OS, []), InvalidValueException::class],
            'quantidade zero' => [fn () => $this->stock->reserve(self::OS, [new StockRequest($active, '0')]), InvalidValueException::class],
            'os inválida' => [fn () => $this->stock->reserve('os-1', [new StockRequest($active, '1')]), InvalidValueException::class],
            'item inexistente' => [fn () => $this->stock->reserve(self::OS, [new StockRequest(InventoryItemId::generate()->value, '1')]), InventoryItemNotFoundException::class],
        ];

        foreach ($cases as $label => [$case, $exception]) {
            try {
                $case();
                self::fail("Deveria rejeitar: $label");
            } catch (DomainException $e) {
                self::assertInstanceOf($exception, $e, $label);
            }
        }

        $this->expectException(InventoryItemNotFoundException::class);
        $this->stock->checkAvailability([new StockRequest(InventoryItemId::generate()->value, '1')]);
    }

    public function test_inactive_item_blocks_reservation(): void
    {
        $id = $this->item('PASTILHA', '0');
        $item = $this->items->findById(InventoryItemId::from($id));
        self::assertNotNull($item);
        $item->deactivate();
        $this->items->save($item);

        $this->expectException(InactiveInventoryItemException::class);
        $this->stock->reserve(self::OS, [new StockRequest($id, '1')]);
    }

    public function test_additional_repair_then_consumption(): void
    {
        $pad = $this->item('PASTILHA', '10');
        $this->stock->reserve(self::OS, [new StockRequest($pad, '2')]);
        $this->stock->reserve(self::OS, [new StockRequest($pad, '1')]);

        $this->stock->consume(self::OS);
        $this->stock->consume(self::OS);

        $item = $this->items->findById(InventoryItemId::from($pad));
        self::assertNotNull($item);
        self::assertSame('7.000', $item->physicalQuantity()->toDecimal());
        self::assertTrue($item->reservedQuantity()->isZero());
        self::assertCount(1, $this->events->ofType(StockConsumed::class), 'segunda baixa é ignorada (idempotente)');
        self::assertSame(ReservationStatus::CONSUMED, $this->reservations->findForServiceOrder(ServiceOrderReference::from(self::OS))?->status());

        $this->expectException(DomainException::class);
        $this->stock->release(self::OS);
    }

    public function test_cancellation_releases_reservation(): void
    {
        $pad = $this->item('PASTILHA', '10');
        $this->stock->reserve(self::OS, [new StockRequest($pad, '3')]);

        $this->stock->release(self::OS);
        $this->stock->release(self::OS);

        self::assertSame('10.000', $this->available($pad));
        self::assertCount(1, $this->events->ofType(StockReservationReleased::class));

        try {
            $this->stock->consume(self::OS);
            self::fail('Reserva liberada não pode ser baixada.');
        } catch (DomainException $e) {
            self::assertStringContainsString('liberada', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->stock->reserve(self::OS, [new StockRequest($pad, '1')]);
    }

    public function test_consume_and_release_without_reservation_do_nothing(): void
    {
        $this->stock->consume(self::OS);
        $this->stock->release(self::OS);

        self::assertSame([], $this->events->published);
    }
}
