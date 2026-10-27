<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Inventory\Domain\Event\StockConsumed;
use Oficina\Inventory\Domain\Event\StockReservationReleased;
use Oficina\Inventory\Domain\Event\StockReserved;
use Oficina\Shared\Domain\Event\RecordsEvents;
use Oficina\Shared\Domain\Exception\DomainException;

/**
 * Agregado: o que está reservado no estoque para uma Ordem de Serviço (uma reserva por OS).
 * Guarda as linhas para que a baixa e a liberação atuem exatamente sobre o que foi reservado.
 * O saldo de cada item continua sendo responsabilidade do agregado InventoryItem.
 */
final class StockReservation
{
    use RecordsEvents;

    /** @param array<string, ReservationLine> $lines indexadas pelo id do item */
    private function __construct(
        public readonly ServiceOrderReference $serviceOrder,
        private ReservationStatus $status,
        private array $lines,
    ) {}

    public static function open(ServiceOrderReference $serviceOrder): self
    {
        return new self($serviceOrder, ReservationStatus::ACTIVE, []);
    }

    /** @param list<ReservationLine> $lines */
    public static function reconstitute(ServiceOrderReference $serviceOrder, ReservationStatus $status, array $lines): self
    {
        $indexed = [];
        foreach ($lines as $line) {
            $indexed[$line->itemId->value] = $line;
        }

        return new self($serviceOrder, $status, $indexed);
    }

    /**
     * Acrescenta linhas já reservadas nos itens (aprovação do orçamento ou de reparo adicional).
     *
     * @param  list<ReservationLine>  $lines
     */
    public function add(array $lines): void
    {
        $this->guardActive();

        if ($lines === []) {
            throw new DomainException('Informe ao menos uma peça/insumo para reservar.');
        }

        foreach ($lines as $line) {
            $current = $this->lines[$line->itemId->value] ?? null;
            $this->lines[$line->itemId->value] = new ReservationLine(
                $line->itemId,
                $current === null ? $line->quantity : $current->quantity->add($line->quantity),
            );
        }

        $this->record(new StockReserved($this->serviceOrder->value, self::serialize($lines)));
    }

    public function consume(): void
    {
        $this->guardActive();
        $this->status = ReservationStatus::CONSUMED;
        $this->record(new StockConsumed($this->serviceOrder->value, self::serialize($this->lines())));
    }

    public function release(): void
    {
        $this->guardActive();
        $this->status = ReservationStatus::RELEASED;
        $this->record(new StockReservationReleased($this->serviceOrder->value, self::serialize($this->lines())));
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === ReservationStatus::ACTIVE;
    }

    /** @return list<ReservationLine> */
    public function lines(): array
    {
        return array_values($this->lines);
    }

    private function guardActive(): void
    {
        if (! $this->isActive()) {
            throw new DomainException(sprintf(
                'A reserva de estoque desta ordem de serviço já foi encerrada (%s).',
                $this->status->value,
            ));
        }
    }

    /**
     * @param  list<ReservationLine>  $lines
     * @return list<array{item_id: string, quantity: string}>
     */
    private static function serialize(array $lines): array
    {
        return array_map(static fn (ReservationLine $line): array => $line->toArray(), $lines);
    }
}
