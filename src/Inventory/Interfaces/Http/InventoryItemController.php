<?php

declare(strict_types=1);

namespace Oficina\Inventory\Interfaces\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Oficina\Inventory\Application\AdjustStock;
use Oficina\Inventory\Application\ChangeInventoryItemPrice;
use Oficina\Inventory\Application\DeactivateInventoryItem;
use Oficina\Inventory\Application\FindInventoryItem;
use Oficina\Inventory\Application\InventoryItemView;
use Oficina\Inventory\Application\ListInventoryItems;
use Oficina\Inventory\Application\ListItemsBelowMinimum;
use Oficina\Inventory\Application\ListStockMovements;
use Oficina\Inventory\Application\ReactivateInventoryItem;
use Oficina\Inventory\Application\ReceiveStock;
use Oficina\Inventory\Application\RegisterInventoryItem;
use Oficina\Inventory\Application\StockMovementView;
use Oficina\Inventory\Application\UpdateInventoryItem;
use Oficina\Shared\Application\Page;

/**
 * Peças e insumos com controle de estoque (protegido por JWT). Sem regra de negócio: apenas traduz HTTP <-> casos de uso.
 * Administrador cadastra e movimenta; mecânico consulta. Erros de domínio viram 404/409/422 em bootstrap/app.php.
 *
 * @tags Peças e Insumos
 */
final class InventoryItemController extends Controller
{
    /**
     * Listar peças e insumos
     *
     * Catálogo paginado, ordenado por nome, com saldo físico, reservado e disponível.
     * Busca em nome, SKU e fabricante (`search`); filtro por `type`; desativados só com `include_inactive=1`.
     *
     * @response array{data: list<array{id: string, sku: string, name: string, type: string, unit: string, price: string, available_quantity: string}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function index(Request $request, ListInventoryItems $listItems): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'in:PECA,INSUMO,peca,insumo'],
            'include_inactive' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListInventoryItems::MAX_PER_PAGE],
        ]);

        $result = $listItems->execute(
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 15),
            isset($validated['search']) ? (string) $validated['search'] : null,
            isset($validated['type']) ? (string) $validated['type'] : null,
            $request->boolean('include_inactive'),
        );

        return $this->paginated($request, $result, static fn (InventoryItemView $view): InventoryItemResource => new InventoryItemResource($view));
    }

    /**
     * Cadastrar peça/insumo
     *
     * SKU único (409 se repetido). Estoque inicial informado vira a primeira movimentação do extrato.
     * Unidade `UN` só aceita quantidades inteiras.
     */
    public function store(StoreInventoryItemRequest $request, RegisterInventoryItem $register): JsonResponse
    {
        return (new InventoryItemResource($register->execute($request->toInput())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Consultar peça/insumo
     *
     * Retorna 404 se não existir.
     */
    public function show(string $part, FindInventoryItem $find): InventoryItemResource
    {
        return new InventoryItemResource($find->execute($part));
    }

    /**
     * Atualizar dados da peça/insumo
     *
     * Substitui nome, descrição, fabricante, tipo, unidade e estoque mínimo. A unidade só muda com estoque zerado.
     */
    public function update(string $part, UpdateInventoryItemRequest $request, UpdateInventoryItem $update): InventoryItemResource
    {
        return new InventoryItemResource($update->execute($part, $request->toInput()));
    }

    /**
     * Alterar preço de venda
     *
     * Orçamentos já emitidos mantêm o preço da época.
     */
    public function changePrice(string $part, Request $request, ChangeInventoryItemPrice $changePrice): InventoryItemResource
    {
        $validated = $request->validate([
            /** Novo preço em reais. @example 49.90 */
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);

        return new InventoryItemResource($changePrice->execute($part, (string) $validated['price']));
    }

    /**
     * Registrar entrada de estoque
     *
     * Soma ao saldo físico. `reference` opcional: número da nota fiscal.
     */
    public function receive(string $part, Request $request, ReceiveStock $receiveStock): InventoryItemResource
    {
        $validated = $request->validate([
            /** @example 5 */
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            /** @example NF-12345 */
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return new InventoryItemResource($receiveStock->execute(
            $part,
            (string) $validated['quantity'],
            isset($validated['reference']) ? (string) $validated['reference'] : null,
            isset($validated['reason']) ? (string) $validated['reason'] : null,
        ));
    }

    /**
     * Ajustar estoque (inventário)
     *
     * Informa a quantidade física contada. Motivo obrigatório; não pode ficar abaixo do reservado para OS.
     */
    public function adjust(string $part, Request $request, AdjustStock $adjustStock): InventoryItemResource
    {
        $validated = $request->validate([
            /** Quantidade física contada. @example 8 */
            'counted_quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            /** @example Contagem mensal */
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return new InventoryItemResource($adjustStock->execute($part, (string) $validated['counted_quantity'], (string) $validated['reason']));
    }

    /**
     * Inativar peça/insumo
     *
     * Exclusão lógica: OS antigas continuam referenciando o item. Retorna 422 se houver estoque reservado.
     */
    public function destroy(string $part, DeactivateInventoryItem $deactivate): Response
    {
        $deactivate->execute($part);

        return response()->noContent();
    }

    /**
     * Reativar peça/insumo
     */
    public function activate(string $part, ReactivateInventoryItem $reactivate): InventoryItemResource
    {
        return new InventoryItemResource($reactivate->execute($part));
    }

    /**
     * Extrato de movimentações
     *
     * Mais recentes primeiro: estoque inicial, entradas, ajustes, reservas, liberações e baixas.
     *
     * @response array{data: list<array{id: string, type: string, quantity: string, physical_after: string, reserved_after: string, reason: string|null, reference: string|null, occurred_at: string}>, meta: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function movements(string $part, Request $request, ListStockMovements $listMovements): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListStockMovements::MAX_PER_PAGE],
        ]);

        $result = $listMovements->execute($part, (int) ($validated['page'] ?? 1), (int) ($validated['per_page'] ?? 30));

        return $this->paginated($request, $result, static fn (StockMovementView $view): StockMovementResource => new StockMovementResource($view));
    }

    /**
     * Lista de reposição
     *
     * Itens ativos com disponível menor ou igual ao estoque mínimo.
     *
     * @response array{data: list<array{id: string, sku: string, name: string, available_quantity: string, minimum_quantity: string}>}
     */
    public function restock(Request $request, ListItemsBelowMinimum $listBelowMinimum): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (InventoryItemView $view): array => (new InventoryItemResource($view))->toArray($request),
                $listBelowMinimum->execute(),
            ),
        ]);
    }

    /**
     * @template T
     *
     * @param  Page<T>  $page
     * @param  callable(T): JsonResource  $toResource
     */
    private function paginated(Request $request, Page $page, callable $toResource): JsonResponse
    {
        return response()->json([
            'data' => array_map(static fn (mixed $item): array => (array) $toResource($item)->toArray($request), $page->items),
            'meta' => [
                'page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
