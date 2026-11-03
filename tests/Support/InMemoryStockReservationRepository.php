<?php

declare(strict_types=1);

namespace Tests\Support;

use Oficina\Inventory\Domain\ServiceOrderReference;
use Oficina\Inventory\Domain\StockReservation;
use Oficina\Inventory\Domain\StockReservationRepository;

final class InMemoryStockReservationRepository implements StockReservationRepository
{
    /** @var array<string, StockReservation> */
    private array $reservations = [];

    public function lockForServiceOrder(ServiceOrderReference $serviceOrder): ?StockReservation
    {
        return $this->findForServiceOrder($serviceOrder);
    }

    public function findForServiceOrder(ServiceOrderReference $serviceOrder): ?StockReservation
    {
        return isset($this->reservations[$serviceOrder->value]) ? clone $this->reservations[$serviceOrder->value] : null;
    }

    public function save(StockReservation $reservation): void
    {
        $stored = clone $reservation;
        $stored->pullEvents();
        $this->reservations[$reservation->serviceOrder->value] = $stored;
    }
}
