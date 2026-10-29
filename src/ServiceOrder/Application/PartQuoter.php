<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\Inventory\Application\Contract\StockAvailabilityView;
use Oficina\Inventory\Application\Contract\StockRequest;
use Oficina\Inventory\Domain\Exception\InactiveInventoryItemException;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\ServiceOrder\Domain\PartLine;
use Oficina\ServiceOrder\Domain\Quantity;
use Oficina\Shared\Domain\ValueObject\Money;

/**
 * Monta as linhas de peça consultando o Estoque. Política "Bloquear peça/insumo que não tem em estoque":
 * peça inativa ou sem saldo disponível não entra no orçamento (a reserva de verdade só acontece na aprovação).
 */
final readonly class PartQuoter
{
    public function __construct(private ServiceOrderStock $stock) {}

    /**
     * @param  list<array{inventory_item_id: string, quantity: string}>  $requests
     * @return list<PartLine>
     */
    public function quote(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $quantities = array_map(static fn (array $r): Quantity => Quantity::of($r['quantity']), $requests);
        $availability = $this->stock->checkAvailability(array_map(
            static fn (array $r, Quantity $q): StockRequest => new StockRequest($r['inventory_item_id'], $q->toDecimal()),
            $requests,
            $quantities,
        ));

        $shortages = [];
        foreach ($availability as $view) {
            if (! $view->active) {
                throw new InactiveInventoryItemException($view->sku);
            }

            if (! $view->sufficient) {
                $shortages[] = ['item_id' => $view->itemId, 'sku' => $view->sku, 'requested' => $view->requested, 'available' => $view->available];
            }
        }

        if ($shortages !== []) {
            throw new InsufficientStockException($shortages);
        }

        $byItem = [];
        foreach ($availability as $view) {
            $byItem[$view->itemId] = $view;
        }

        return array_map(static function (array $request, Quantity $quantity) use ($byItem): PartLine {
            /** @var StockAvailabilityView $view */
            $view = $byItem[strtolower(trim($request['inventory_item_id']))];

            return PartLine::quote($view->itemId, $view->sku, $view->name, $view->unit, $quantity, Money::fromCents($view->unitPriceCents));
        }, $requests, $quantities);
    }
}
