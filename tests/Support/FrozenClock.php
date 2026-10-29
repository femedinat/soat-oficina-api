<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use Oficina\Shared\Application\Clock;

/** Relógio controlado pelo teste (prazo de 48 h, cronômetro de execução). */
final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-10-29 09:00:00')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
