<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use Oficina\Inventory\Application\AdjustStock;
use Oficina\Inventory\Application\ChangeInventoryItemPrice;
use Oficina\Inventory\Application\DeactivateInventoryItem;
use Oficina\Inventory\Application\FindInventoryItem;
use Oficina\Inventory\Application\ListInventoryItems;
use Oficina\Inventory\Application\ListItemsBelowMinimum;
use Oficina\Inventory\Application\ListStockMovements;
use Oficina\Inventory\Application\LockedItemCommand;
use Oficina\Inventory\Application\ReactivateInventoryItem;
use Oficina\Inventory\Application\ReceiveStock;
use Oficina\Inventory\Application\RegisterInventoryItem;
use Oficina\Inventory\Application\RegisterInventoryItemInput;
use Oficina\Inventory\Application\UpdateInventoryItem;
use Oficina\Inventory\Application\UpdateInventoryItemInput;
use Oficina\Inventory\Domain\Event\InventoryItemPriceChanged;
use Oficina\Inventory\Domain\Event\InventoryItemRegistered;
use Oficina\Inventory\Domain\Event\StockReceived;
use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;
use Oficina\Inventory\Domain\Exception\SkuAlreadyRegisteredException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImmediateTransactionManager;
use Tests\Support\InMemoryInventoryItemRepository;
use Tests\Support\SpyEventPublisher;

final class InventoryUseCasesTest extends TestCase
{
    private const string UNKNOWN = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

    private InMemoryInventoryItemRepository $items;

    private ImmediateTransactionManager $transaction;

    private SpyEventPublisher $events;

    private LockedItemCommand $command;

    protected function setUp(): void
    {
        $this->items = new InMemoryInventoryItemRepository;
        $this->transaction = new ImmediateTransactionManager;
        $this->events = new SpyEventPublisher;
        $this->command = new LockedItemCommand($this->items, $this->transaction, $this->events);
    }

    private function register(string $sku = 'FILTRO-OLEO', string $name = 'Filtro de óleo', string $type = 'PECA', string $initial = '10', string $minimum = '2'): string
    {
        return (new RegisterInventoryItem($this->items, $this->transaction, $this->events))
            ->execute(new RegisterInventoryItemInput($sku, $name, $type, 'UN', '45,90', $initial, $minimum, 'Para motor 1.0', 'Mann'))
            ->id;
    }

    public function test_registers_item_and_publishes_event(): void
    {
        $id = $this->register();

        $view = (new FindInventoryItem($this->items))->execute($id);
        self::assertSame('FILTRO-OLEO', $view->sku);
        self::assertSame('45.90', $view->price);
        self::assertSame(4590, $view->priceCents);
        self::assertSame('10.000', $view->availableQuantity);
        self::assertSame('Mann', $view->manufacturer);
        self::assertSame('Para motor 1.0', $view->description);
        self::assertFalse($view->belowMinimum);
        self::assertTrue($view->active);
        self::assertSame([InventoryItemRegistered::class], $this->events->names());
        self::assertCount(1, $this->items->movements);
    }

    public function test_rejects_duplicate_sku_regardless_of_case(): void
    {
        $this->register();

        $this->expectException(SkuAlreadyRegisteredException::class);
        $this->register('filtro-oleo');
    }

    public function test_invalid_input_never_reaches_repository(): void
    {
        try {
            $this->register(type: 'SERVICO');
            self::fail('Tipo inválido deveria falhar.');
        } catch (InvalidValueException) {
        }

        self::assertSame(0, $this->transaction->runs);
    }

    public function test_updates_item_under_lock(): void
    {
        $id = $this->register();

        $view = (new UpdateInventoryItem($this->command))->execute($id, new UpdateInventoryItemInput('Filtro de óleo PH6017', 'PECA', 'UN', '3', null, 'Fram'));

        self::assertSame('Filtro de óleo PH6017', $view->name);
        self::assertSame('Fram', $view->manufacturer);
        self::assertSame('3.000', $view->minimumQuantity);
        self::assertSame([$id], $this->items->lockedIds);
    }

    public function test_changes_price(): void
    {
        $id = $this->register();

        $view = (new ChangeInventoryItemPrice($this->command))->execute($id, '49.90');

        self::assertSame('49.90', $view->price);
        self::assertCount(1, $this->events->ofType(InventoryItemPriceChanged::class));
    }

    public function test_receives_and_adjusts_stock_with_statement(): void
    {
        $id = $this->register();

        (new ReceiveStock($this->command))->execute($id, '5', 'NF-998');
        $view = (new AdjustStock($this->command))->execute($id, '14', 'Contagem mensal');

        self::assertSame('14.000', $view->physicalQuantity);
        self::assertCount(1, $this->events->ofType(StockReceived::class));

        $statement = (new ListStockMovements($this->items))->execute($id, 1, 2);
        self::assertSame(3, $statement->total);
        self::assertSame(2, $statement->lastPage());
        self::assertSame('AJUSTE_NEGATIVO', $statement->items[0]->type);
        self::assertSame('Contagem mensal', $statement->items[0]->reason);
        self::assertSame('ENTRADA', $statement->items[1]->type);
        self::assertSame('NF-998', $statement->items[1]->reference);
        self::assertNotSame('', $statement->items[0]->occurredAt);
    }

    public function test_deactivates_and_reactivates(): void
    {
        $id = $this->register();

        self::assertFalse((new DeactivateInventoryItem($this->command))->execute($id)->active);
        self::assertSame(0, (new ListInventoryItems($this->items))->execute()->total, 'desativado some do catálogo');
        self::assertSame(1, (new ListInventoryItems($this->items))->execute(includeInactive: true)->total);
        self::assertTrue((new ReactivateInventoryItem($this->command))->execute($id)->active);
    }

    public function test_lists_with_filters_and_clamps_pagination(): void
    {
        $this->register('FILTRO-AR', 'Filtro de ar');
        $this->register('OLEO-5W30', 'Óleo 5W30', 'INSUMO');
        $list = new ListInventoryItems($this->items);

        $all = $list->execute(page: 0, perPage: 1000);
        self::assertSame(1, $all->page);
        self::assertSame(ListInventoryItems::MAX_PER_PAGE, $all->perPage);
        self::assertSame(2, $all->total);

        self::assertSame('OLEO-5W30', $list->execute(type: 'insumo')->items[0]->sku);
        self::assertSame('FILTRO-AR', $list->execute(search: ' ar ')->items[0]->sku);
        self::assertSame(2, $list->execute(search: '  ', type: '')->total);
    }

    public function test_lists_items_below_minimum(): void
    {
        $this->register('FILTRO-AR', 'Filtro de ar', initial: '10', minimum: '2');
        $low = $this->register('VELA-NGK', 'Vela de ignição', initial: '1', minimum: '4');

        $restock = (new ListItemsBelowMinimum($this->items))->execute();

        self::assertCount(1, $restock);
        self::assertSame($low, $restock[0]->id);
        self::assertTrue($restock[0]->belowMinimum);
    }

    public function test_unknown_item_returns_not_found(): void
    {
        foreach ([
            fn () => (new FindInventoryItem($this->items))->execute(self::UNKNOWN),
            fn () => (new ReceiveStock($this->command))->execute(self::UNKNOWN, '1'),
            fn () => (new ListStockMovements($this->items))->execute(self::UNKNOWN),
        ] as $call) {
            try {
                $call();
                self::fail('Deveria lançar 404.');
            } catch (InventoryItemNotFoundException $e) {
                self::assertSame('Peça/insumo não encontrado.', $e->getMessage());
            }
        }
    }
}
