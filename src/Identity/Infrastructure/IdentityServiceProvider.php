<?php

declare(strict_types=1);

namespace Oficina\Identity\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Oficina\Identity\Application\TokenIssuer;
use Oficina\Identity\Domain\CustomerDirectory;
use Oficina\Identity\Domain\PasswordHasher;
use Oficina\Identity\Domain\SystemUserRepository;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SystemUserRepository::class, EloquentSystemUserRepository::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(TokenIssuer::class, JwtTokenIssuer::class);
        $this->app->bind(CustomerDirectory::class, EloquentCustomerDirectory::class);
    }
}
