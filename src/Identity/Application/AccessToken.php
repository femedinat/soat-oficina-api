<?php

declare(strict_types=1);

namespace Oficina\Identity\Application;

final readonly class AccessToken
{
    public function __construct(
        public string $token,
        public int $expiresInSeconds,
        public string $type = 'bearer',
    ) {}
}
