<?php

declare(strict_types=1);

namespace Oficina\Identity\Interfaces\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Oficina\Identity\Application\CreateUser;

/**
 * @tags Usuários
 */
final class UserController extends Controller
{
    /**
     * Criar usuário
     *
     * Restrito a usuários com perfil Administrador. Perfis: `administrator`, `mechanic`, `customer`.
     * O perfil `customer` exige `customer_id` de um cliente existente (relação 1:1);
     * os demais perfis não aceitam `customer_id`.
     *
     * Retorna 403 sem permissão, 409 se o e-mail ou o cliente já possuírem usuário e 422 para dados inválidos.
     *
     * @response 201 array{data: array{id: int, name: string, email: string, role: 'administrator'|'mechanic'|'customer', customer_id: string|null}}
     */
    public function store(CreateUserRequest $request, CreateUser $createUser): JsonResponse
    {
        $view = $createUser->execute($request->toInput());

        return response()->json(['data' => [
            'id' => $view->id,
            'name' => $view->name,
            'email' => $view->email,
            'role' => $view->role,
            'customer_id' => $view->customerId,
        ]], Response::HTTP_CREATED);
    }
}
