<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Oficina\Customer\Infrastructure\CustomerServiceProvider;
use Oficina\Identity\Infrastructure\IdentityServiceProvider;
use Oficina\Inventory\Infrastructure\InventoryServiceProvider;
use Oficina\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    CustomerServiceProvider::class,
    InventoryServiceProvider::class,
];
