<?php

declare(strict_types=1);

namespace Oficina\Catalog\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Oficina\Catalog\Application\CatalogServiceQuotes;
use Oficina\Catalog\Application\Contract\ServiceCatalog;
use Oficina\Catalog\Application\PartLookup;
use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Inventory\Domain\Event\InventoryItemDeactivated;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServiceRepository::class, EloquentServiceRepository::class);
        $this->app->bind(PartLookup::class, InventoryPartLookup::class);
        $this->app->bind(ServiceCatalog::class, CatalogServiceQuotes::class);
    }

    public function boot(): void
    {
        Event::listen(InventoryItemDeactivated::class, RemoveDeactivatedPartListener::class);
    }
}
