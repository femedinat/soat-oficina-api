<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain;

final readonly class ServiceFilter
{
    public function __construct(
        public ?string $search = null,
        public ?string $category = null,
        public bool $includeInactive = false,
    ) {}
}
