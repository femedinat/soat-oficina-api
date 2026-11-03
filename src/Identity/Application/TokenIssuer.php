<?php

declare(strict_types=1);

namespace Oficina\Identity\Application;

use Oficina\Identity\Domain\SystemUser;

/** Porta para emissão do token de acesso (implementada na infraestrutura com JWT). */
interface TokenIssuer
{
    public function issueFor(SystemUser $user): AccessToken;
}
