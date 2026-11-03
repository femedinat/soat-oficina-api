<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Shared\Application\Page;

interface InventoryItemRepository
{
    /**
     * Persiste o item e grava as movimentações novas na mesma transação.
     *
     * @throws Exception\SkuAlreadyRegisteredException
     */
    public function save(InventoryItem $item): void;

    public function findById(InventoryItemId $id): ?InventoryItem;

    public function findBySku(Sku $sku): ?InventoryItem;

    /**
     * Carrega e bloqueia (SELECT ... FOR UPDATE) os itens, sempre na ordem do id para evitar deadlock
     * entre duas OS que reservam os mesmos itens. Deve ser chamado dentro de uma transação.
     *
     * @param  list<InventoryItemId>  $ids
     * @return array<string, InventoryItem> indexado pelo id; ids inexistentes ficam de fora
     */
    public function lockForUpdate(array $ids): array;

    /** @return Page<InventoryItem> */
    public function paginate(int $page, int $perPage, InventoryItemFilter $filter): Page;

    /** @return list<InventoryItem> itens ativos com disponível <= mínimo */
    public function belowMinimum(): array;

    /** @return Page<StockMovement> mais recentes primeiro */
    public function movementsOf(InventoryItemId $id, int $page, int $perPage): Page;
}
