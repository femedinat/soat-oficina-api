<?php

declare(strict_types=1);

namespace Oficina\Shared\Infrastructure;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TransactionManager::class,
            fn ($app): TransactionManager => new DatabaseTransactionManager($app->make(ConnectionInterface::class)),
        );
        $this->app->bind(
            EventPublisher::class,
            fn ($app): EventPublisher => new LaravelEventPublisher(
                $app->make(Dispatcher::class),
                $app->make(Connection::class),
            ),
        );
    }
}
