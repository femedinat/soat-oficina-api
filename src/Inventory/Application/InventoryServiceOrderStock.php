<?php

declare(strict_types=1);

namespace Oficina\Inventory\Application;

use Oficina\Inventory\Application\Contract\ServiceOrderStock;
use Oficina\Inventory\Application\Contract\StockAvailabilityView;
use Oficina\Inventory\Application\Contract\StockRequest;
use Oficina\Inventory\Domain\Event\StockReservationRefused;
use Oficina\Inventory\Domain\Exception\InactiveInventoryItemException;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\Inventory\Domain\Exception\InventoryItemNotFoundException;
use Oficina\Inventory\Domain\InventoryItem;
use Oficina\Inventory\Domain\InventoryItemId;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\Quantity;
use Oficina\Inventory\Domain\ReservationLine;
use Oficina\Inventory\Domain\ReservationStatus;
use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\StockReservation;
use Oficina\Inventory\Domain\StockReservationRepository;
use Oficina\Shared\Application\EventPublisher;
use Oficina\Shared\Application\TransactionManager;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;

/**
 * Implementação do contrato com a OS. Reserva, baixa e liberação seguem o mesmo roteiro:
 * transação -> bloqueio da reserva da OS -> bloqueio dos itens em ordem de id -> valida tudo -> altera tudo.
 */
final readonly class InventoryServiceOrderStock implements ServiceOrderStock
{
    public function __construct(
        private InventoryItemRepository $items,
        private StockReservationRepository $reservations,
        private TransactionManager $transaction,
        private EventPublisher $events,
    ) {}

    public function checkAvailability(array $requests): array
    {
        $lines = $this->toLines($requests);
        $found = [];

        foreach ($lines as $line) {
            $found[$line->itemId->value] = $this->items->findById($line->itemId)
                ?? throw new InventoryItemNotFoundException($line->itemId->value);
        }

        return array_map(static function (ReservationLine $line) use ($found): StockAvailabilityView {
            $item = $found[$line->itemId->value];

            return new StockAvailabilityView(
                itemId: $item->id->value,
                sku: $item->sku()->value,
                name: $item->name(),
                type: $item->type()->value,
                unit: $item->unit()->value,
                unitPriceCents: $item->price()->cents,
                requested: $line->quantity->toDecimal(),
                available: $item->availableQuantity()->toDecimal(),
                active: $item->isActive(),
                sufficient: $item->canReserve($line->quantity),
            );
        }, $lines);
    }

    public function reserve(string $serviceOrderId, array $requests): void
    {
        $serviceOrder = ServiceOrderReference::from($serviceOrderId);
        $lines = $this->toLines($requests);

        try {
            $this->transaction->run(function () use ($serviceOrder, $lines): void {
                $reservation = $this->reservations->lockForServiceOrder($serviceOrder) ?? StockReservation::open($serviceOrder);
                $items = $this->lockItems($lines);

                $this->guardAllReservable($lines, $items);

                foreach ($lines as $line) {
                    $items[$line->itemId->value]->reserve($line->quantity, $serviceOrder);
                }

                $reservation->add($lines);
                $this->persist($items, $reservation);
            });
        } catch (InsufficientStockException $e) {
            // A transação foi desfeita; o fato "reserva recusada" é publicado fora dela.
            $this->events->publish(new StockReservationRefused(
                $serviceOrder->value,
                array_map(static fn (array $s): array => [
                    'item_id' => $s['item_id'],
                    'requested' => $s['requested'],
                    'available' => $s['available'],
                ], $e->shortages),
            ));

            throw $e;
        }
    }

    public function consume(string $serviceOrderId): void
    {
        $serviceOrder = ServiceOrderReference::from($serviceOrderId);

        $this->transaction->run(function () use ($serviceOrder): void {
            $reservation = $this->reservations->lockForServiceOrder($serviceOrder);

            if ($reservation === null || $reservation->status() === ReservationStatus::CONSUMED) {
                return;
            }

            $this->guardNotEnded($reservation, ReservationStatus::RELEASED, 'liberada; não há o que baixar');
            $items = $this->lockItems($reservation->lines());

            foreach ($reservation->lines() as $line) {
                $items[$line->itemId->value]->consume($line->quantity, $serviceOrder);
            }

            $reservation->consume();
            $this->persist($items, $reservation);
        });
    }

    public function release(string $serviceOrderId): void
    {
        $serviceOrder = ServiceOrderReference::from($serviceOrderId);

        $this->transaction->run(function () use ($serviceOrder): void {
            $reservation = $this->reservations->lockForServiceOrder($serviceOrder);

            if ($reservation === null || $reservation->status() === ReservationStatus::RELEASED) {
                return;
            }

            $this->guardNotEnded($reservation, ReservationStatus::CONSUMED, 'baixada; as peças já foram consumidas');
            $items = $this->lockItems($reservation->lines());

            foreach ($reservation->lines() as $line) {
                $items[$line->itemId->value]->releaseReservation($line->quantity, $serviceOrder);
            }

            $reservation->release();
            $this->persist($items, $reservation);
        });
    }

    /**
     * Valida e agrupa os pedidos: o mesmo item pedido duas vezes vira uma linha com a soma.
     *
     * @param  list<StockRequest>  $requests
     * @return list<ReservationLine>
     */
    private function toLines(array $requests): array
    {
        if ($requests === []) {
            throw new InvalidValueException('Informe ao menos uma peça/insumo.');
        }

        $lines = [];
        foreach ($requests as $request) {
            $id = InventoryItemId::from($request->itemId);
            $quantity = Quantity::of($request->quantity);

            if ($quantity->isZero()) {
                throw new InvalidValueException('A quantidade de cada peça/insumo deve ser maior que zero.');
            }

            $current = $lines[$id->value] ?? null;
            $lines[$id->value] = new ReservationLine($id, $current === null ? $quantity : $current->quantity->add($quantity));
        }

        return array_values($lines);
    }

    /**
     * @param  list<ReservationLine>  $lines
     * @return array<string, InventoryItem>
     */
    private function lockItems(array $lines): array
    {
        $items = $this->items->lockForUpdate(array_map(static fn (ReservationLine $l): InventoryItemId => $l->itemId, $lines));

        foreach ($lines as $line) {
            if (! isset($items[$line->itemId->value])) {
                throw new InventoryItemNotFoundException($line->itemId->value);
            }
        }

        return $items;
    }

    /**
     * Confere todos os itens antes de alterar qualquer um, para reportar todas as faltas de uma vez.
     *
     * @param  list<ReservationLine>  $lines
     * @param  array<string, InventoryItem>  $items
     */
    private function guardAllReservable(array $lines, array $items): void
    {
        $shortages = [];

        foreach ($lines as $line) {
            $item = $items[$line->itemId->value];

            if (! $item->isActive()) {
                throw new InactiveInventoryItemException($item->sku()->value);
            }

            if ($line->quantity->greaterThan($item->availableQuantity())) {
                $shortages[] = $item->shortageFor($line->quantity);
            }
        }

        if ($shortages !== []) {
            throw new InsufficientStockException($shortages);
        }
    }

    private function guardNotEnded(StockReservation $reservation, ReservationStatus $status, string $detail): void
    {
        if ($reservation->status() === $status) {
            throw new DomainException(sprintf('A reserva de estoque desta ordem de serviço já foi %s.', $detail));
        }
    }

    /** @param array<string, InventoryItem> $items */
    private function persist(array $items, StockReservation $reservation): void
    {
        foreach ($items as $item) {
            $this->items->save($item);
            $this->events->publish(...$item->pullEvents());
        }

        $this->reservations->save($reservation);
        $this->events->publish(...$reservation->pullEvents());
    }
}
