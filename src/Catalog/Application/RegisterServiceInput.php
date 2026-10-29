<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

final readonly class RegisterServiceInput
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $category,
        public string $laborPrice,
        public int $estimatedMinutes,
    ) {}
}
