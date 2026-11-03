<?php

declare(strict_types=1);

namespace Oficina\Shared\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Oficina\Shared\Application\TransactionManager;

final readonly class DatabaseTransactionManager implements TransactionManager
{
    public function __construct(private ConnectionInterface $connection) {}

    public function run(callable $work): mixed
    {
        return $this->connection->transaction(static fn () => $work());
    }
}
