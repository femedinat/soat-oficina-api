<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Oficina\Customer\Application\ActivateCustomer;
use Oficina\Customer\Application\CustomerView;
use Oficina\Customer\Application\DeactivateCustomer;
use Oficina\Customer\Application\FindCustomer;
use Oficina\Customer\Application\FindCustomerByDocument;
use Oficina\Customer\Application\ListCustomers;
use Oficina\Customer\Application\RegisterCustomer;
use Oficina\Customer\Application\UpdateCustomer;
use Oficina\Customer\Application\UpdateCustomerContact;

/**
 * Ciclo de vida completo de clientes (protegido por JWT e controle de acesso por papel).
 * Sem regra de negócio: apenas traduz HTTP <-> casos de uso.
 * Erros de domínio são convertidos em 404/409/422 em bootstrap/app.php.
 *
 * @tags Clientes
 */
final class CustomerController extends Controller
{
    /**
     * Listar clientes
     *
     * Paginado, ordenado por nome. Suporta filtro por CPF/CNPJ (`document`) e status (`only_active`). `per_page` máximo: 100.
     *
     * @response array{data: list<array{id: string, name: string, document_type: string, document: string, email: string, phone: string|null, active: bool}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function index(Request $request, ListCustomers $listCustomers): JsonResponse
    {
        $validated = $request->validate([
            'document' => ['sometimes', 'string'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListCustomers::MAX_PER_PAGE],
        ]);

        $onlyActive = $request->filled('only_active')
            ? $request->boolean('only_active')
            : null;

        $result = $listCustomers->execute(
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
            isset($validated['document']) ? (string) $validated['document'] : null,
            $onlyActive,
        );

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
     * Consultar cliente por ID
     *
     * Retorna 404 se não existir.
     */
    public function show(string $customer, FindCustomer $findCustomer): CustomerResource
    {
        return new CustomerResource($findCustomer->execute($customer));
    }

    /**
     * Consultar cliente por CPF/CNPJ
     *
     * Retorna 404 se não existir e 422 se o documento informado for inválido.
     */
    public function showByDocument(string $document, FindCustomerByDocument $findCustomerByDocument): CustomerResource
    {
        return new CustomerResource($findCustomerByDocument->execute($document));
    }

    /**
     * Atualizar dados do cliente
     *
     * Substitui todos os dados do cliente. Retorna 404 se não existir, 409 se o documento
     * pertencer a outro cliente e 422 se o cliente estiver inativo.
     */
    public function update(string $customer, CustomerRequest $request, UpdateCustomer $updateCustomer): CustomerResource
    {
        return new CustomerResource($updateCustomer->execute($customer, $request->toInput()));
    }

    /**
     * Atualizar contato do cliente (telefone e e-mail)
     *
     * Atualiza apenas o contato do cliente e dispara confirmação de atualização por e-mail.
     */
    public function updateContact(
        string $customer,
        UpdateCustomerContactRequest $request,
        UpdateCustomerContact $updateCustomerContact,
    ): CustomerResource {
        return new CustomerResource($updateCustomerContact->execute($customer, $request->toInput()));
    }

    /**
     * Reativar cliente
     *
     * Reativa um cliente previamente inativado.
     */
    public function activate(string $customer, ActivateCustomer $activateCustomer): CustomerResource
    {
        return new CustomerResource($activateCustomer->execute($customer));
    }

    /**
     * Inativar cliente
     *
     * Realiza a inativação lógica do cliente, preservando e arquivando o histórico de serviços e veículos.
     */
    public function destroy(string $customer, DeactivateCustomer $deactivateCustomer): Response
    {
        $deactivateCustomer->execute($customer);

        return response()->noContent();
    }
}
