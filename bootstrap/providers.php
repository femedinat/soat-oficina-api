<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Oficina\Customer\Infrastructure\CustomerServiceProvider;
use Oficina\Identity\Infrastructure\IdentityServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    CustomerServiceProvider::class,
];
