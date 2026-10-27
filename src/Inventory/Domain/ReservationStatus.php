<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

/** Situação da reserva de estoque de uma OS. Consumida e liberada são finais. */
enum ReservationStatus: string
{
    case ACTIVE = 'ATIVA';
    case CONSUMED = 'CONSUMIDA';
    case RELEASED = 'LIBERADA';
}
