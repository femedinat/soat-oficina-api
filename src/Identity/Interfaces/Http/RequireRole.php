<?php

declare(strict_types=1);

namespace Oficina\Identity\Interfaces\Http;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Restringe a rota aos perfis informados: `role:administrator,mechanic`. Usar após `auth:api`. */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('api');

        if (! $user instanceof User || ! in_array($user->role->value, $roles, true)) {
            throw new AccessDeniedHttpException('Você não tem permissão para realizar esta ação.');
        }

        return $next($request);
    }
}
