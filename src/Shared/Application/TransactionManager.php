<?php

declare(strict_types=1);

namespace Oficina\Shared\Application;

/** Fronteira transacional dos casos de uso. Chamadas aninhadas participam da transação externa. */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function run(callable $work): mixed;
}
