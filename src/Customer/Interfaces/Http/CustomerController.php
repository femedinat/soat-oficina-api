<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Oficina\Customer\Application\CustomerView;
use Oficina\Customer\Application\DeleteCustomer;
use Oficina\Customer\Application\FindCustomer;
use Oficina\Customer\Application\ListCustomers;
use Oficina\Customer\Application\RegisterCustomer;
use Oficina\Customer\Application\UpdateCustomer;

/**
 * CRUD de clientes (protegido por JWT). Sem regra de negócio: apenas traduz HTTP <-> casos de uso.
 * Erros de domínio são convertidos em 404/409/422 em bootstrap/app.php.
 *
 * @tags Clientes
 */
final class CustomerController extends Controller
{
    /**
     * Listar clientes
     *
     * Paginado, ordenado por nome. `per_page` máximo: 100.
     *
     * @response array{data: list<array{id: string, name: string, document_type: string, document: string, email: string, phone: string|null}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function index(Request $request, ListCustomers $listCustomers): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListCustomers::MAX_PER_PAGE],
        ]);

        $result = $listCustomers->execute((int) ($validated['page'] ?? 1), (int) ($validated['per_page'] ?? 15));

        return response()->json([
            'data' => array_map(
                static fn (CustomerView $view): array => (new CustomerResource($view))->toArray($request),
                $result->items,
            ),
            'meta' => [
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Cadastrar cliente
     *
     * CPF/CNPJ é obrigatório, deve ser válido (dígitos verificadores) e único.
     * Retorna 422 para dados inválidos e 409 se o documento já estiver cadastrado.
     */
    public function store(CustomerRequest $request, RegisterCustomer $registerCustomer): JsonResponse
    {
        $view = $registerCustomer->execute($request->toInput());

        return (new CustomerResource($view))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Consultar cliente
     *
     * Retorna 404 se não existir.
     */
    public function show(string $customer, FindCustomer $findCustomer): CustomerResource
    {
        return new CustomerResource($findCustomer->execute($customer));
    }

    /**
     * Atualizar cliente
     *
     * Substitui todos os dados do cliente. Retorna 404 se não existir e 409 se o documento
     * pertencer a outro cliente.
     */
    public function update(string $customer, CustomerRequest $request, UpdateCustomer $updateCustomer): CustomerResource
    {
        return new CustomerResource($updateCustomer->execute($customer, $request->toInput()));
    }

    /**
     * Excluir cliente
     *
     * Retorna 404 se não existir.
     */
    public function destroy(string $customer, DeleteCustomer $deleteCustomer): Response
    {
        $deleteCustomer->execute($customer);

        return response()->noContent();
    }
}
