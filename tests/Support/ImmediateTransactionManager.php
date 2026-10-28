<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Shared\Application\TransactionManager;

/** Executa direto e conta quantas transações foram abertas. */
final class ImmediateTransactionManager implements TransactionManager
{
    public int $runs = 0;

    public function run(callable $work): mixed
    {
        $this->runs++;

        return $work();
    }
}
