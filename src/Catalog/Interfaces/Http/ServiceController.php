<?php

declare(strict_types=1);

namespace Oficina\Catalog\Interfaces\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Oficina\Catalog\Application\AdjustServiceEstimatedTime;
use Oficina\Catalog\Application\ChangeServicePrice;
use Oficina\Catalog\Application\DeactivateService;
use Oficina\Catalog\Application\FindService;
use Oficina\Catalog\Application\LinkStandardPart;
use Oficina\Catalog\Application\ListServices;
use Oficina\Catalog\Application\ReactivateService;
use Oficina\Catalog\Application\RegisterService;
use Oficina\Catalog\Application\ServiceView;
use Oficina\Catalog\Application\UnlinkStandardPart;
use Oficina\Catalog\Application\UpdateService;
use Oficina\Catalog\Domain\Service;

/**
 * Catálogo de serviços da oficina (protegido por JWT). Sem regra de negócio: apenas traduz HTTP <-> casos de uso.
 * Administrador cadastra e altera; mecânico e cliente consultam. Erros de domínio viram 404/409/422 em bootstrap/app.php.
 *
 * @tags Serviços
 */
final class ServiceController extends Controller
{
    /**
     * Listar serviços
     *
     * Catálogo paginado, ordenado por nome. Busca em nome e código (`search`), filtro por `category`;
     * inativos só com `include_inactive=1`.
     *
     * @response array{data: list<array{id: string, code: string, name: string, category: string|null, labor_price: string, estimated_minutes: int, active: bool}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function index(Request $request, ListServices $listServices): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:60'],
            'include_inactive' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListServices::MAX_PER_PAGE],
        ]);

        $result = $listServices->execute(
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
            isset($validated['search']) ? (string) $validated['search'] : null,
            isset($validated['category']) ? (string) $validated['category'] : null,
            $request->boolean('include_inactive'),
        );

        return response()->json([
            'data' => array_map(static fn (ServiceView $view): array => (new ServiceResource($view))->toArray($request), $result->items),
            'meta' => [
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage(),
            ],
        ]);
    }

    /**
     * Cadastrar serviço
     *
     * Código e nome únicos (409 se repetidos). O código não muda depois.
     */
    public function store(StoreServiceRequest $request, RegisterService $register): JsonResponse
    {
        return (new ServiceResource($register->execute($request->toInput())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Consultar serviço
     */
    public function show(string $service, FindService $find): ServiceResource
    {
        return new ServiceResource($find->execute($service));
    }

    /**
     * Atualizar dados do serviço
     *
     * Substitui nome, descrição e categoria. Preço e tempo estimado têm rotas próprias.
     */
    public function update(string $service, Request $request, UpdateService $update): ServiceResource
    {
        $validated = $request->validate([
            /** @example Troca de óleo e filtro */
            'name' => ['required', 'string', 'max:'.Service::NAME_MAX_LENGTH],
            'description' => ['nullable', 'string', 'max:'.Service::DESCRIPTION_MAX_LENGTH],
            'category' => ['nullable', 'string', 'max:'.Service::CATEGORY_MAX_LENGTH],
        ]);

        return new ServiceResource($update->execute(
            $service,
            (string) $validated['name'],
            isset($validated['description']) ? (string) $validated['description'] : null,
            isset($validated['category']) ? (string) $validated['category'] : null,
        ));
    }

    /**
     * Alterar preço da mão de obra
     *
     * OS já orçadas mantêm o preço da época.
     */
    public function changePrice(string $service, Request $request, ChangeServicePrice $changePrice): ServiceResource
    {
        $validated = $request->validate([
            /** @example 135.00 */
            'labor_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        return new ServiceResource($changePrice->execute($service, (string) $validated['labor_price']));
    }

    /**
     * Ajustar tempo estimado
     */
    public function adjustEstimatedTime(string $service, Request $request, AdjustServiceEstimatedTime $adjust): ServiceResource
    {
        $validated = $request->validate([
            /** Minutos. @example 45 */
            'estimated_minutes' => ['required', 'integer', 'min:1', 'max:'.Service::MAX_ESTIMATED_MINUTES],
        ]);

        return new ServiceResource($adjust->execute($service, (int) $validated['estimated_minutes']));
    }

    /**
     * Vincular peça/insumo padrão
     *
     * A peça precisa existir e estar ativa no estoque. Vincular de novo atualiza a quantidade.
     */
    public function linkPart(string $service, Request $request, LinkStandardPart $link): ServiceResource
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'uuid'],
            /** @example 4.5 */
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);

        return new ServiceResource($link->execute($service, (string) $validated['inventory_item_id'], (string) $validated['quantity']));
    }

    /**
     * Desvincular peça/insumo padrão
     */
    public function unlinkPart(string $service, string $part, UnlinkStandardPart $unlink): ServiceResource
    {
        return new ServiceResource($unlink->execute($service, $part));
    }

    /**
     * Inativar serviço
     *
     * Exclusão lógica: some dos novos orçamentos, OS antigas mantêm o histórico.
     */
    public function destroy(string $service, DeactivateService $deactivate): Response
    {
        $deactivate->execute($service);

        return response()->noContent();
    }

    /**
     * Reativar serviço
     */
    public function activate(string $service, ReactivateService $reactivate): ServiceResource
    {
        return new ServiceResource($reactivate->execute($service));
    }
}
