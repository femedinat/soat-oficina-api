<?php

declare(strict_types=1);

namespace Oficina\Identity\Interfaces\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Oficina\Identity\Application\AuthenticateSystemUser;

/**
 * @tags Autenticação
 */
final class AuthController extends Controller
{
    /**
     * Login do administrador
     *
     * Autentica com e-mail e senha e devolve o token JWT (envie em `Authorization: Bearer <token>`).
     *
     * @unauthenticated
     *
     * @response array{access_token: string, token_type: string, expires_in: int}
     */
    public function login(LoginRequest $request, AuthenticateSystemUser $authenticate): JsonResponse
    {
        $token = $authenticate->execute(
            (string) $request->string('email'),
            (string) $request->string('password'),
        );

        return response()->json([
            'access_token' => $token->token,
            'token_type' => $token->type,
            'expires_in' => $token->expiresInSeconds,
        ]);
    }

    /**
     * Usuário autenticado
     *
     * @response array{id: int, name: string, email: string, role: 'administrator'|'mechanic'|'customer', customer_id: string|null}
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return response()->json([
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'customer_id' => $user->customer_id,
        ]);
    }

    /**
     * Logout
     *
     * Invalida o token atual (blacklist).
     */
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
