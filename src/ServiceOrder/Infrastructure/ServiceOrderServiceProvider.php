<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Oficina\ServiceOrder\Application\CustomerDirectory;
use Oficina\ServiceOrder\Application\CustomerNotificationPolicy;
use Oficina\ServiceOrder\Application\ExecutionTimeStatistics;
use Oficina\ServiceOrder\Application\ServiceOrderNotifier;
use Oficina\ServiceOrder\Application\VehicleRegistry;
use Oficina\ServiceOrder\Domain\Event\BudgetSent;
use Oficina\ServiceOrder\Domain\Event\ServiceOrderCanceled;
use Oficina\ServiceOrder\Domain\Event\ServicesFinished;
use Oficina\ServiceOrder\Domain\ServiceOrderRepository;

final class ServiceOrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ServiceOrderRepository::class, EloquentServiceOrderRepository::class);
        $this->app->bind(CustomerDirectory::class, CustomerContextDirectory::class);
        $this->app->bind(VehicleRegistry::class, VehicleContextRegistry::class);
        $this->app->bind(ServiceOrderNotifier::class, LaravelMailServiceOrderNotifier::class);
        $this->app->bind(ExecutionTimeStatistics::class, QueryExecutionTimeStatistics::class);
    }

    public function boot(): void
    {
        Event::listen(BudgetSent::class, [CustomerNotificationPolicy::class, 'onBudgetSent']);
        Event::listen(ServicesFinished::class, [CustomerNotificationPolicy::class, 'onServicesFinished']);
        Event::listen(ServiceOrderCanceled::class, [CustomerNotificationPolicy::class, 'onCanceled']);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireOverdueApprovalsCommand::class]);
        }
    }
}
