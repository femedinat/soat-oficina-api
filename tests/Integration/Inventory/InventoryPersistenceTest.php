<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Oficina\Inventory\Application\AdjustStock;
use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\Inventory\Application\Contract\StockRequest;
use Oficina\Inventory\Application\ListInventoryItems;
use Oficina\Inventory\Application\ListItemsBelowMinimum;
use Oficina\Inventory\Application\ListStockMovements;
use Oficina\Inventory\Application\ReceiveStock;
use Oficina\Inventory\Application\RegisterInventoryItem;
use Oficina\Inventory\Application\RegisterInventoryItemInput;
use Oficina\Inventory\Application\UpdateInventoryItem;
use Oficina\Inventory\Application\UpdateInventoryItemInput;
use Oficina\Inventory\Domain\Event\InventoryItemRegistered;
use Oficina\Inventory\Domain\Event\StockConsumed;
use Oficina\Inventory\Domain\Event\StockReservationRefused;
use Oficina\Inventory\Domain\Event\StockReserved;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\Inventory\Domain\Exception\SkuAlreadyRegisteredException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\ItemType;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\Sku;
use Oficina\Inventory\Domain\StockReservationRepository;
use Oficina\Inventory\Domain\UnitOfMeasure;
use Oficina\Shared\Application\TransactionManager;
use Oficina\Shared\Domain\ValueObject\Money;
use RuntimeException;
use Tests\TestCase;

final class InventoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const string OS = '7d0f3a9e-1c2b-4d5e-8f60-123456789abc';

    private function register(string $sku, string $name, string $initial = '10', string $minimum = '2', string $type = 'PECA', string $unit = 'UN'): string
    {
        return $this->app->make(RegisterInventoryItem::class)
            ->execute(new RegisterInventoryItemInput($sku, $name, $type, $unit, '25.50', $initial, $minimum, null, 'Bosch'))
            ->id;
    }

    /** @return array<string, mixed> */
    private function row(string $id): array
    {
        return (array) DB::table('inventory_items')->where('id', $id)->first();
    }

    public function test_persists_item_with_initial_stock_movement(): void
    {
        Event::fake([InventoryItemRegistered::class]);

        $id = $this->register('FILTRO-AR', 'Filtro de ar', '4.000');

        $this->assertDatabaseHas('inventory_items', ['id' => $id, 'sku' => 'FILTRO-AR', 'price_cents' => 2550, 'physical_quantity' => '4.000', 'active' => true]);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $id, 'type' => 'ESTOQUE_INICIAL', 'quantity' => '4.000']);
        Event::assertDispatched(InventoryItemRegistered::class, static fn (InventoryItemRegistered $e): bool => $e->itemId === $id);
    }

    public function test_round_trips_aggregate_through_repository(): void
    {
        $repository = $this->app->make(InventoryItemRepository::class);
        $item = InventoryItem::register(Sku::from('OLEO-5W30'), 'Óleo 5W30', 'Sintético', 'Mobil', ItemType::SUPPLY, UnitOfMeasure::LITER, Money::fromDecimal('39.90'), Quantity::of('12.250'), Quantity::of('3.5'));
        $repository->save($item);

        $loaded = $repository->findBySku(Sku::from('oleo-5w30'));

        self::assertNotNull($loaded);
        self::assertTrue($loaded->id->equals($item->id));
        self::assertSame('Sintético', $loaded->description());
        self::assertSame(UnitOfMeasure::LITER, $loaded->unit());
        self::assertSame('12.250', $loaded->physicalQuantity()->toDecimal());
        self::assertSame('3.500', $loaded->minimumQuantity()->toDecimal());
        self::assertSame(3990, $loaded->price()->cents);
        self::assertSame([], $loaded->pullEvents());
        self::assertSame([], $repository->lockForUpdate([]));
    }

    public function test_unique_index_is_the_last_barrier_against_duplicate_sku(): void
    {
        $repository = $this->app->make(InventoryItemRepository::class);
        $repository->save(InventoryItem::register(Sku::from('VELA'), 'Vela', null, null, ItemType::PART, UnitOfMeasure::UNIT, Money::zero(), Quantity::of(1), Quantity::zero()));

        $this->expectException(SkuAlreadyRegisteredException::class);
        // Salva direto (sem a checagem do caso de uso), como numa corrida entre duas requisições.
        $repository->save(InventoryItem::register(Sku::from('VELA'), 'Vela 2', null, null, ItemType::PART, UnitOfMeasure::UNIT, Money::zero(), Quantity::of(1), Quantity::zero()));
    }

    public function test_database_checks_reject_reserved_above_physical(): void
    {
        $id = $this->register('DISCO', 'Disco de freio', '2');

        $this->expectException(QueryException::class);
        DB::table('inventory_items')->where('id', $id)->update(['reserved_quantity' => '3']);
    }

    public function test_catalog_filters_search_literally(): void
    {
        $this->register('FILTRO-AR', 'Filtro de ar');
        $this->register('FILTRO-OLEO', 'Filtro de óleo 100%');
        $this->register('OLEO-5W30', 'Óleo 5W30', type: 'INSUMO', unit: 'L');
        $list = $this->app->make(ListInventoryItems::class);

        self::assertSame(2, $list->execute(search: 'filtro')->total);
        self::assertSame(3, $list->execute(search: 'BOSCH')->total, 'busca também pelo fabricante');
        self::assertSame(['FILTRO-OLEO'], array_map(static fn ($v) => $v->sku, $list->execute(search: '100%')->items));
        self::assertSame(0, $list->execute(search: '_')->total, 'curinga do LIKE é tratado como texto');
        self::assertSame(['OLEO-5W30'], array_map(static fn ($v) => $v->sku, $list->execute(type: 'INSUMO')->items));

        $page = $list->execute(page: 2, perPage: 2);
        self::assertSame(3, $page->total);
        self::assertCount(1, $page->items);
        self::assertSame('Óleo 5W30', $page->items[0]->name);
    }

    public function test_restock_list_and_statement_order(): void
    {
        $id = $this->register('PASTILHA', 'Pastilha', '5', '2');
        $this->register('FILTRO', 'Filtro', '10', '2');

        $this->app->make(ReceiveStock::class)->execute($id, '1', 'NF-1');
        $this->app->make(AdjustStock::class)->execute($id, '2', 'Avaria no transporte');
        $this->app->make(UpdateInventoryItem::class)->execute($id, new UpdateInventoryItemInput('Pastilha dianteira', 'PECA', 'UN', '2'));

        $restock = $this->app->make(ListItemsBelowMinimum::class)->execute();
        self::assertSame(['PASTILHA'], array_map(static fn ($v) => $v->sku, $restock));

        $statement = $this->app->make(ListStockMovements::class)->execute($id);
        self::assertSame(['AJUSTE_NEGATIVO', 'ENTRADA', 'ESTOQUE_INICIAL'], array_map(static fn ($v) => $v->type, $statement->items));
        self::assertSame('2.000', $statement->items[0]->physicalAfter);
        self::assertSame('Pastilha dianteira', $this->row($id)['name']);
    }

    public function test_reserve_consume_flow_persists_balances_and_reservation(): void
    {
        Event::fake([StockReserved::class, StockConsumed::class]);
        $pad = $this->register('PASTILHA', 'Pastilha', '4');
        $oil = $this->register('OLEO', 'Óleo', '10', '1', 'INSUMO', 'L');
        $stock = $this->app->make(ServiceOrderStock::class);

        $stock->reserve(self::OS, [new StockRequest($pad, '4'), new StockRequest($oil, '4.5')]);

        self::assertSame('4.000', $this->row($pad)['reserved_quantity']);
        self::assertSame('4.500', $this->row($oil)['reserved_quantity']);
        $this->assertDatabaseHas('stock_reservations', ['service_order_id' => self::OS, 'status' => 'ATIVA']);
        $this->assertDatabaseCount('stock_reservation_lines', 2);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $oil, 'type' => 'RESERVA', 'reference' => self::OS]);
        Event::assertDispatched(StockReserved::class);

        $stock->consume(self::OS);

        self::assertSame('0.000', $this->row($pad)['physical_quantity']);
        self::assertSame('5.500', $this->row($oil)['physical_quantity']);
        self::assertSame('0.000', $this->row($oil)['reserved_quantity']);
        $reservation = $this->app->make(StockReservationRepository::class)->findForServiceOrder(ServiceOrderReference::from(self::OS));
        self::assertSame('CONSUMIDA', $reservation?->status()->value);
        self::assertCount(2, $reservation->lines());
        Event::assertDispatched(StockConsumed::class);
    }

    public function test_insufficient_stock_rolls_back_every_item(): void
    {
        Event::fake([StockReserved::class, StockReservationRefused::class]);
        $pad = $this->register('PASTILHA', 'Pastilha', '4');
        $disc = $this->register('DISCO', 'Disco', '1');
        $stock = $this->app->make(ServiceOrderStock::class);

        try {
            $stock->reserve(self::OS, [new StockRequest($pad, '2'), new StockRequest($disc, '2')]);
            self::fail('Deveria faltar estoque.');
        } catch (InsufficientStockException $e) {
            self::assertSame('DISCO', $e->shortages[0]['sku']);
        }

        self::assertSame('0.000', $this->row($pad)['reserved_quantity']);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseMissing('stock_movements', ['type' => 'RESERVA']);
        Event::assertNotDispatched(StockReserved::class);
        Event::assertDispatched(StockReservationRefused::class);
    }

    public function test_reservation_joins_caller_transaction_and_events_wait_for_commit(): void
    {
        Event::fake([StockReserved::class]);
        $pad = $this->register('PASTILHA', 'Pastilha', '4');
        $stock = $this->app->make(ServiceOrderStock::class);

        try {
            // Simula a aprovação do orçamento na OS falhando depois de reservar: tudo é desfeito.
            $this->app->make(TransactionManager::class)->run(function () use ($stock, $pad): void {
                $stock->reserve(self::OS, [new StockRequest($pad, '3')]);

                throw new RuntimeException('falha ao salvar a OS');
            });
        } catch (RuntimeException) {
        }

        self::assertSame('0.000', $this->row($pad)['reserved_quantity']);
        $this->assertDatabaseCount('stock_reservations', 0);
        Event::assertNotDispatched(StockReserved::class);
    }

    public function test_release_after_cancellation(): void
    {
        $pad = $this->register('PASTILHA', 'Pastilha', '4');
        $stock = $this->app->make(ServiceOrderStock::class);
        $stock->reserve(self::OS, [new StockRequest($pad, '1')]);
        $stock->reserve(self::OS, [new StockRequest($pad, '2')]);

        $stock->release(self::OS);

        self::assertSame('0.000', $this->row($pad)['reserved_quantity']);
        $this->assertDatabaseHas('stock_reservations', ['service_order_id' => self::OS, 'status' => 'LIBERADA']);
        $this->assertDatabaseHas('stock_reservation_lines', ['inventory_item_id' => $pad, 'quantity' => '3.000']);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $pad, 'type' => 'LIBERACAO_RESERVA', 'quantity' => '3.000']);
    }
}
