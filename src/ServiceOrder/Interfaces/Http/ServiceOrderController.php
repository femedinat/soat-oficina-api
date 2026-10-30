<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Interfaces\Http;

use App\Http\Controllers\Controller;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Oficina\Identity\Domain\Role;
use Oficina\ServiceOrder\Application\AddPartToOrder;
use Oficina\ServiceOrder\Application\AddServiceToOrder;
use Oficina\ServiceOrder\Application\DecideBudget;
use Oficina\ServiceOrder\Application\ExecuteServiceOrder;
use Oficina\ServiceOrder\Application\FindServiceOrder;
use Oficina\ServiceOrder\Application\GetExecutionTimeReport;
use Oficina\ServiceOrder\Application\ListServiceOrders;
use Oficina\ServiceOrder\Application\OpenServiceOrder;
use Oficina\ServiceOrder\Application\RemoveOrderItem;
use Oficina\ServiceOrder\Application\RequestAdditionalRepair;
use Oficina\ServiceOrder\Application\SendBudget;
use Oficina\ServiceOrder\Application\ServiceOrderView;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Ordem de Serviço (protegido por JWT). Sem regra de negócio: apenas traduz HTTP <-> casos de uso.
 * Cliente autenticado só enxerga e decide as próprias OS (outra OS = 404). Erros de domínio viram 404/409/422.
 *
 * @tags Ordens de Serviço
 */
final class ServiceOrderController extends Controller
{
    /**
     * Listar ordens de serviço
     *
     * Mais recentes primeiro. Filtros: `status`, `customer_id`, `vehicle_id`. Para o perfil Cliente,
     * a lista traz só as OS dele, qualquer que seja o filtro.
     *
     * @response array{data: list<array{id: string, status: string, status_label: string, customer_id: string, vehicle_id: string, budget: array{total: string}}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function index(Request $request, ListServiceOrders $list): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'customer_id' => ['sometimes', 'uuid'],
            'vehicle_id' => ['sometimes', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListServiceOrders::MAX_PER_PAGE],
        ]);

        $result = $list->execute(
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
            isset($validated['status']) ? (string) $validated['status'] : null,
            isset($validated['customer_id']) ? (string) $validated['customer_id'] : null,
            isset($validated['vehicle_id']) ? (string) $validated['vehicle_id'] : null,
            $this->customerScope($request),
        );

        return response()->json([
            'data' => array_map(static fn (ServiceOrderView $view): array => (new ServiceOrderResource($view))->toArray($request), $result->items),
            'meta' => [
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Abrir ordem de serviço
     *
     * Identifica o cliente pelo CPF/CNPJ e o veículo pela placa. Placa nova é cadastrada já vinculada
     * ao cliente (marca, modelo e ano obrigatórios nesse caso). A OS nasce "Recebida".
     */
    public function store(OpenServiceOrderRequest $request, OpenServiceOrder $open): JsonResponse
    {
        return (new ServiceOrderResource($open->execute((string) $request->validated('document'), $request->vehicleData())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Consultar ordem de serviço
     *
     * Itens, orçamento, aprovação pendente e histórico de status.
     */
    public function show(string $order, Request $request, FindServiceOrder $find): ServiceOrderResource
    {
        return new ServiceOrderResource($find->execute($order, $this->customerScope($request)));
    }

    /**
     * Adicionar serviço
     *
     * Copia nome, preço e tempo estimado do catálogo (preço congelado). O primeiro item coloca a OS
     * "Em diagnóstico". Serviço inativo é recusado. Retorna as peças padrão do serviço como sugestão.
     *
     * @response array{data: array{id: string, status: string}, suggested_parts: list<array{inventory_item_id: string, quantity: string}>}
     */
    public function addService(string $order, Request $request, AddServiceToOrder $add): JsonResponse
    {
        $validated = $request->validate(['service_id' => ['required', 'uuid']]);
        $result = $add->execute($order, (string) $validated['service_id']);

        return response()->json([
            'data' => (new ServiceOrderResource($result['order']))->toArray($request),
            'suggested_parts' => $result['suggested_parts'],
        ], Response::HTTP_CREATED);
    }

    /**
     * Remover serviço
     */
    public function removeService(string $order, string $line, RemoveOrderItem $remove): ServiceOrderResource
    {
        return new ServiceOrderResource($remove->service($order, $line));
    }

    /**
     * Adicionar peça/insumo
     *
     * Bloqueia peça inativa ou sem saldo disponível (422 com a lista do que falta). Incluir a mesma peça
     * de novo soma a quantidade. A reserva no estoque só acontece na aprovação do orçamento.
     */
    public function addPart(string $order, Request $request, AddPartToOrder $add): JsonResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'uuid'],
            /** @example 4.5 */
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);

        return (new ServiceOrderResource($add->execute($order, (string) $validated['inventory_item_id'], (string) $validated['quantity'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remover peça/insumo
     */
    public function removePart(string $order, string $line, RemoveOrderItem $remove): ServiceOrderResource
    {
        return new ServiceOrderResource($remove->part($order, $line));
    }

    /**
     * Enviar orçamento ao cliente
     *
     * Exige ao menos um serviço. A OS passa a "Aguardando aprovação" com prazo de 48 h e o cliente recebe e-mail.
     */
    public function sendBudget(string $order, SendBudget $send): ServiceOrderResource
    {
        return new ServiceOrderResource($send->execute($order));
    }

    /**
     * Devolver orçamento ao diagnóstico
     *
     * Para ajustar itens antes da aprovação (ex.: falta de estoque).
     */
    public function returnToDiagnosis(string $order, SendBudget $send): ServiceOrderResource
    {
        return new ServiceOrderResource($send->returnToDiagnosis($order));
    }

    /**
     * Aprovar orçamento
     *
     * Reserva as peças (tudo ou nada), inicia o cronômetro e coloca a OS "Em execução".
     * Sem estoque, responde 422 e a OS continua aguardando aprovação.
     */
    public function approve(string $order, Request $request, DecideBudget $decide): ServiceOrderResource
    {
        return new ServiceOrderResource($decide->approve($order, $this->customerScope($request)));
    }

    /**
     * Rejeitar orçamento
     *
     * A OS é cancelada (status "Cancelada") e o veículo vai para retirada.
     */
    public function reject(string $order, Request $request, DecideBudget $decide): ServiceOrderResource
    {
        return new ServiceOrderResource($decide->reject($order, $this->customerScope($request)));
    }

    /**
     * Solicitar reparo adicional
     *
     * Durante a execução: os novos itens entram pendentes e o cliente recebe um novo pedido de aprovação (48 h).
     */
    public function requestAdditionalRepair(string $order, Request $request, RequestAdditionalRepair $requestRepair): ServiceOrderResource
    {
        $validated = $request->validate([
            'services' => ['sometimes', 'array', 'max:20'],
            'services.*' => ['uuid'],
            'parts' => ['sometimes', 'array', 'max:50'],
            'parts.*.inventory_item_id' => ['required', 'uuid'],
            'parts.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);

        /** @var list<string> $services */
        $services = array_values(array_map('strval', (array) ($validated['services'] ?? [])));
        /** @var list<array{inventory_item_id: string, quantity: string}> $parts */
        $parts = array_values(array_map(static fn (array $part): array => [
            'inventory_item_id' => (string) $part['inventory_item_id'],
            'quantity' => (string) $part['quantity'],
        ], (array) ($validated['parts'] ?? [])));

        return new ServiceOrderResource($requestRepair->execute($order, $services, $parts));
    }

    /**
     * Autorizar reparo adicional
     *
     * Reserva as peças extras (tudo ou nada) e retoma a execução.
     */
    public function authorizeAdditional(string $order, Request $request, DecideBudget $decide): ServiceOrderResource
    {
        return new ServiceOrderResource($decide->authorizeAdditional($order, $this->customerScope($request)));
    }

    /**
     * Recusar reparo adicional
     *
     * Mantém o escopo original e retoma a execução.
     */
    public function refuseAdditional(string $order, Request $request, DecideBudget $decide): ServiceOrderResource
    {
        return new ServiceOrderResource($decide->refuseAdditional($order, $this->customerScope($request)));
    }

    /**
     * Finalizar execução
     *
     * Para o cronômetro, dá baixa no estoque reservado e avisa o cliente que o veículo está pronto.
     */
    public function finish(string $order, ExecuteServiceOrder $execute): ServiceOrderResource
    {
        return new ServiceOrderResource($execute->finish($order));
    }

    /**
     * Registrar entrega do veículo
     */
    public function deliver(string $order, ExecuteServiceOrder $execute): ServiceOrderResource
    {
        return new ServiceOrderResource($execute->deliver($order));
    }

    /**
     * Cancelar ordem de serviço
     *
     * Possível até a finalização. Se o orçamento já tinha sido aprovado, a reserva de estoque é liberada.
     */
    public function cancel(string $order, ExecuteServiceOrder $execute): ServiceOrderResource
    {
        return new ServiceOrderResource($execute->cancel($order));
    }

    /**
     * Tempo médio de execução
     *
     * Da aprovação até "Serviços finalizados", geral e por serviço, comparado ao tempo estimado.
     * OS canceladas ficam fora. Período opcional por data de finalização (`from`, `to`).
     *
     * @response array{data: array{period: array{from: string|null, to: string|null}, overall: array{orders: int, average_minutes: int|null, min_minutes: int|null, max_minutes: int|null, average_estimated_minutes: int|null}, services: list<array{service_id: string, code: string, name: string, orders: int, average_minutes: int, estimated_minutes: int, deviation_minutes: int}>}}
     */
    public function executionTime(Request $request, GetExecutionTimeReport $report): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        return response()->json(['data' => $report->execute(
            isset($validated['from']) ? new DateTimeImmutable((string) $validated['from']) : null,
            isset($validated['to']) ? new DateTimeImmutable((string) $validated['to']) : null,
        )]);
    }

    /** Perfil Cliente: escopo nas próprias OS. Sem cliente vinculado, não há o que ver. */
    private function customerScope(Request $request): ?string
    {
        $user = $request->user('api');

        if (! $user instanceof User || $user->role !== Role::CUSTOMER) {
            return null;
        }

        return $user->customer_id ?? throw new AccessDeniedHttpException('Usuário cliente sem cadastro de cliente vinculado.');
    }
}
