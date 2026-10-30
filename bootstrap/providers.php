<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Oficina\Catalog\Infrastructure\CatalogServiceProvider;
use Oficina\Customer\Infrastructure\CustomerServiceProvider;
use Oficina\Identity\Infrastructure\IdentityServiceProvider;
use Oficina\Inventory\Infrastructure\InventoryServiceProvider;
use Oficina\ServiceOrder\Infrastructure\ServiceOrderServiceProvider;
use Oficina\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    CustomerServiceProvider::class,
    InventoryServiceProvider::class,
    CatalogServiceProvider::class,
    ServiceOrderServiceProvider::class,
];
