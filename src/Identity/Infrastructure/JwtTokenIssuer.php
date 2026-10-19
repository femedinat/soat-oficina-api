<?php

declare(strict_types=1);

namespace Oficina\Identity\Infrastructure;

use App\Models\User;
use Oficina\Identity\Application\AccessToken;
use Oficina\Identity\Application\TokenIssuer;
use Oficina\Identity\Domain\SystemUser;
use PHPOpenSourceSaver\JWTAuth\JWTAuth;

final readonly class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(private JWTAuth $jwt) {}

    public function issueFor(SystemUser $user): AccessToken
    {
        $model = User::query()->findOrFail((int) $user->id);

        return new AccessToken(
            token: $this->jwt->fromUser($model),
            expiresInSeconds: (int) $this->jwt->factory()->getTTL() * 60,
        );
    }
}
