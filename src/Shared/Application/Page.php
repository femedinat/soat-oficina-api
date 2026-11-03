<?php

declare(strict_types=1);

namespace Oficina\Shared\Application;

/**
 * Página de resultados, independente de framework.
 *
 * @template T
 */
final readonly class Page
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
