<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Oficina\Customer\Domain\CustomerContactNotifier;
use Oficina\Customer\Domain\CustomerRepository;
use Oficina\Customer\Domain\Repository\VehicleRepositoryInterface;
use Oficina\Customer\Infrastructure\Persistence\Eloquent\EloquentVehicleRepository;

final class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
        $this->app->bind(CustomerContactNotifier::class, LaravelMailCustomerContactNotifier::class);
        $this->app->bind(VehicleRepositoryInterface::class, EloquentVehicleRepository::class);
    }
}
