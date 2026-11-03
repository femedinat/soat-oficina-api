<?php

declare(strict_types=1);

namespace Oficina\Shared\Infrastructure;

use DateTimeImmutable;
use Oficina\Shared\Application\Clock;

final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
