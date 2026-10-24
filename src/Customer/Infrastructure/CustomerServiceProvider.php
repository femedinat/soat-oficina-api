<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Oficina\Customer\Domain\CustomerRepository;

final class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
    }
}
