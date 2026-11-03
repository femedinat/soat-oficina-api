<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

/** Tipo de movimentação no extrato do item. Toda alteração de saldo gera exatamente uma. */
enum MovementType: string
{
    case INITIAL_STOCK = 'ESTOQUE_INICIAL';
    case RECEIPT = 'ENTRADA';
    case ADJUSTMENT_IN = 'AJUSTE_POSITIVO';
    case ADJUSTMENT_OUT = 'AJUSTE_NEGATIVO';
    case RESERVATION = 'RESERVA';
    case RESERVATION_RELEASE = 'LIBERACAO_RESERVA';
    case CONSUMPTION = 'BAIXA';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL_STOCK => 'Estoque inicial',
            self::RECEIPT => 'Entrada',
            self::ADJUSTMENT_IN => 'Ajuste positivo',
            self::ADJUSTMENT_OUT => 'Ajuste negativo',
            self::RESERVATION => 'Reserva',
            self::RESERVATION_RELEASE => 'Liberação de reserva',
            self::CONSUMPTION => 'Baixa',
        };
    }
}
