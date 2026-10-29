<?php

declare(strict_types=1);

namespace Oficina\Shared\Application;

use DateTimeImmutable;

/** Relógio injetável: prazos e cronômetros testáveis sem depender da hora real. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
