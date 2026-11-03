<?php

declare(strict_types=1);

namespace Tests\Integration\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\Inventory\Application\Contract\StockRequest;
use Oficina\Inventory\Application\ReceiveStock;
use Oficina\Inventory\Application\RegisterInventoryItem;
use Oficina\Inventory\Application\RegisterInventoryItemInput;
use Tests\TestCase;

/**
 * Prova o bloqueio pessimista com duas conexões reais: enquanto outra transação segura a linha do item,
 * a reserva e a entrada de estoque esperam (aqui, com lock_timeout curto, falham) em vez de ler saldo velho.
 */
final class StockLockingTest extends TestCase
{
    use DatabaseTruncation;

    private const string OS = '7d0f3a9e-1c2b-4d5e-8f60-123456789abc';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.pgsql_concurrent' => config('database.connections.pgsql')]);
    }

    protected function tearDown(): void
    {
        DB::connection('pgsql_concurrent')->rollBack();
        DB::purge('pgsql_concurrent');
        DB::statement('SET lock_timeout = 0');

        parent::tearDown();
    }

    public function test_stock_changes_wait_for_row_lock_held_by_another_transaction(): void
    {
        $id = $this->app->make(RegisterInventoryItem::class)
            ->execute(new RegisterInventoryItemInput('PASTILHA', 'Pastilha', 'PECA', 'UN', '10', '5'))
            ->id;

        $other = DB::connection('pgsql_concurrent');
        $other->beginTransaction();
        $other->select('SELECT id FROM inventory_items WHERE id = ? FOR UPDATE', [$id]);

        DB::statement("SET lock_timeout = '300ms'");

        foreach ([
            'reserva' => fn () => $this->app->make(ServiceOrderStock::class)->reserve(self::OS, [new StockRequest($id, '1')]),
            'entrada' => fn () => $this->app->make(ReceiveStock::class)->execute($id, '1'),
        ] as $operation => $call) {
            try {
                $call();
                self::fail("A $operation deveria aguardar o bloqueio da linha.");
            } catch (QueryException $e) {
                self::assertSame('55P03', $e->getCode(), "$operation: lock_not_available");
            }
        }

        $other->rollBack();

        $this->app->make(ServiceOrderStock::class)->reserve(self::OS, [new StockRequest($id, '1')]);
        self::assertSame('1.000', DB::table('inventory_items')->where('id', $id)->value('reserved_quantity'));
    }
}
