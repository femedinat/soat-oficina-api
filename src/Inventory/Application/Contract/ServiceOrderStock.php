<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application\Contract;

use Oficina\Inventory\Domain\Exception\InactiveInventoryItemException;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;

/**
 * Contrato publicado pelo contexto Estoque para o contexto Ordem de Serviço.
 * A OS só conversa com o estoque por aqui: não acessa tabelas, modelos nem o agregado InventoryItem.
 *
 * Fluxo combinado com o grupo (Event Storming):
 *  - item incluído na OS ............. checkAvailability() (só consulta, não reserva)
 *  - orçamento/reparo aprovado ....... reserve() na MESMA transação da aprovação (tudo ou nada)
 *  - serviços finalizados ............ consume()
 *  - OS cancelada após a aprovação ... release()
 */
interface ServiceOrderStock
{
    /**
     * @param  list<StockRequest>  $requests
     * @return list<StockAvailabilityView> na mesma ordem dos pedidos (itens repetidos são somados)
     *
     * @throws InventoryItemNotFoundException
     */
    public function checkAvailability(array $requests): array;

    /**
     * Reserva todos os itens ou nenhum. Chamadas seguintes para a mesma OS (reparo adicional) somam à reserva.
     * Não é idempotente: envie só os itens novos.
     *
     * @param  list<StockRequest>  $requests
     *
     * @throws InsufficientStockException com todos os itens em falta
     * @throws InactiveInventoryItemException
     * @throws InventoryItemNotFoundException
     */
    public function reserve(string $serviceOrderId, array $requests): void;

    /** Baixa o que foi reservado. Sem reserva (OS só de serviços) ou já baixada: não faz nada. */
    public function consume(string $serviceOrderId): void;

    /** Libera o que foi reservado. Sem reserva ou já liberada: não faz nada. */
    public function release(string $serviceOrderId): void;
}
