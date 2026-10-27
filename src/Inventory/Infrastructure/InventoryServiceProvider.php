<?php

declare(strict_types=1);

namespace Oficina\Inventory\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\Inventory\Application\InventoryServiceOrderStock;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\StockReservationRepository;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryItemRepository::class, EloquentInventoryItemRepository::class);
        $this->app->bind(StockReservationRepository::class, EloquentStockReservationRepository::class);
        $this->app->bind(ServiceOrderStock::class, InventoryServiceOrderStock::class);
    }
}
