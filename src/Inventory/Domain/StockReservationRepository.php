<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

interface StockReservationRepository
{
    /** Carrega e bloqueia a reserva da OS (SELECT ... FOR UPDATE). Deve ser chamado dentro de uma transação. */
    public function lockForServiceOrder(ServiceOrderReference $serviceOrder): ?StockReservation;

    public function findForServiceOrder(ServiceOrderReference $serviceOrder): ?StockReservation;

    public function save(StockReservation $reservation): void;
}
