<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain;

use Oficina\Inventory\Domain\Event\InventoryItemDeactivated;
use Oficina\Inventory\Domain\Event\InventoryItemPriceChanged;
use Oficina\Inventory\Domain\Event\InventoryItemReactivated;
use Oficina\Inventory\Domain\Event\InventoryItemRegistered;
use Oficina\Inventory\Domain\Event\InventoryItemUpdated;
use Oficina\Inventory\Domain\Event\MinimumStockReached;
use Oficina\Inventory\Domain\Event\StockAdjusted;
use Oficina\Inventory\Domain\Event\StockReceived;
use Oficina\Inventory\Domain\Exception\InactiveInventoryItemException;
use Oficina\Inventory\Domain\Exception\InsufficientStockException;
use Oficina\Shared\Domain\Event\RecordsEvents;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Money;

/**
 * Agregado raiz do contexto Estoque: uma peça ou um insumo e o seu saldo.
 *
 * Invariantes:
 *  - disponível = física − reservada, e disponível >= 0 (reservada <= física);
 *  - item desativado não recebe entrada nem reserva;
 *  - unidade "UN" só aceita quantidades inteiras;
 *  - toda alteração de saldo gera um StockMovement (extrato).
 */
final class InventoryItem
{
    use RecordsEvents;

    public const int NAME_MAX_LENGTH = 120;

    public const int DESCRIPTION_MAX_LENGTH = 1000;

    public const int MANUFACTURER_MAX_LENGTH = 120;

    public const int REASON_MAX_LENGTH = 255;

    public const int REFERENCE_MAX_LENGTH = 100;

    /** @var list<StockMovement> */
    private array $newMovements = [];

    private function __construct(
        public readonly InventoryItemId $id,
        private readonly Sku $sku,
        private string $name,
        private ?string $description,
        private ?string $manufacturer,
        private ItemType $type,
        private UnitOfMeasure $unit,
        private Money $price,
        private Quantity $physical,
        private Quantity $reserved,
        private Quantity $minimum,
        private bool $active,
    ) {}

    public static function register(
        Sku $sku,
        string $name,
        ?string $description,
        ?string $manufacturer,
        ItemType $type,
        UnitOfMeasure $unit,
        Money $price,
        Quantity $initialQuantity,
        Quantity $minimumQuantity,
    ): self {
        self::guardUnit($unit, $initialQuantity);
        self::guardUnit($unit, $minimumQuantity);

        $item = new self(
            InventoryItemId::generate(),
            $sku,
            self::guardText($name, self::NAME_MAX_LENGTH, 'nome', required: true) ?? '',
            self::guardText($description, self::DESCRIPTION_MAX_LENGTH, 'descrição'),
            self::guardText($manufacturer, self::MANUFACTURER_MAX_LENGTH, 'fabricante'),
            $type,
            $unit,
            $price,
            $initialQuantity,
            Quantity::zero(),
            $minimumQuantity,
            true,
        );

        $item->record(new InventoryItemRegistered($item->id->value, $sku->value, $item->name, $type->value));

        if (! $initialQuantity->isZero()) {
            $item->recordMovement(MovementType::INITIAL_STOCK, $initialQuantity, 'Estoque inicial no cadastro');
        }

        $item->detectMinimumReached(wasBelowMinimum: false);

        return $item;
    }

    /** Reconstitui o agregado a partir da persistência (sem eventos nem movimentações novas). */
    public static function reconstitute(
        InventoryItemId $id,
        Sku $sku,
        string $name,
        ?string $description,
        ?string $manufacturer,
        ItemType $type,
        UnitOfMeasure $unit,
        Money $price,
        Quantity $physical,
        Quantity $reserved,
        Quantity $minimum,
        bool $active,
    ): self {
        return new self($id, $sku, $name, $description, $manufacturer, $type, $unit, $price, $physical, $reserved, $minimum, $active);
    }

    /** Atualiza os dados cadastrais. SKU e preço têm comandos próprios; unidade só muda com saldo zerado. */
    public function revise(
        string $name,
        ?string $description,
        ?string $manufacturer,
        ItemType $type,
        UnitOfMeasure $unit,
        Quantity $minimumQuantity,
    ): void {
        if ($unit !== $this->unit && (! $this->physical->isZero() || ! $this->reserved->isZero())) {
            throw new DomainException('A unidade de medida só pode ser alterada com o estoque zerado.');
        }

        self::guardUnit($unit, $minimumQuantity);
        $wasBelowMinimum = $this->isBelowMinimum();

        $this->name = self::guardText($name, self::NAME_MAX_LENGTH, 'nome', required: true) ?? '';
        $this->description = self::guardText($description, self::DESCRIPTION_MAX_LENGTH, 'descrição');
        $this->manufacturer = self::guardText($manufacturer, self::MANUFACTURER_MAX_LENGTH, 'fabricante');
        $this->type = $type;
        $this->unit = $unit;
        $this->minimum = $minimumQuantity;

        $this->record(new InventoryItemUpdated($this->id->value));
        $this->detectMinimumReached($wasBelowMinimum);
    }

    /** Altera o preço de venda. Não afeta orçamentos já gerados: a OS congela o preço no item dela. */
    public function changePrice(Money $newPrice): void
    {
        if ($newPrice->equals($this->price)) {
            return;
        }

        $previous = $this->price;
        $this->price = $newPrice;

        $this->record(new InventoryItemPriceChanged($this->id->value, $previous->cents, $newPrice->cents));
    }

    /** Exclusão lógica: OS antigas continuam referenciando o item. Bloqueada enquanto houver reserva. */
    public function deactivate(): void
    {
        if (! $this->active) {
            return;
        }

        if (! $this->reserved->isZero()) {
            throw new DomainException('Não é possível desativar uma peça/insumo com estoque reservado para ordens de serviço.');
        }

        $this->active = false;
        $this->record(new InventoryItemDeactivated($this->id->value));
    }

    public function reactivate(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;
        $this->record(new InventoryItemReactivated($this->id->value));
    }

    /** Entrada de estoque (compra/recebimento). Referência opcional: número da nota fiscal. */
    public function receive(Quantity $quantity, ?string $reference = null, ?string $reason = null): void
    {
        $this->guardActive();
        $this->guardPositive($quantity);

        $this->physical = $this->physical->add($quantity);
        $movement = $this->recordMovement(MovementType::RECEIPT, $quantity, $reason, $reference);

        $this->record(new StockReceived($this->id->value, $quantity->toDecimal(), $this->physical->toDecimal(), $movement->reference));
    }

    /** Ajuste de inventário: informa a quantidade física contada. Motivo obrigatório. */
    public function adjust(Quantity $countedQuantity, string $reason): void
    {
        self::guardUnit($this->unit, $countedQuantity);
        $reason = self::guardText($reason, self::REASON_MAX_LENGTH, 'motivo do ajuste', required: true) ?? '';

        if ($countedQuantity->equals($this->physical)) {
            throw new DomainException('A quantidade contada é igual ao saldo físico: não há o que ajustar.');
        }

        if ($this->reserved->greaterThan($countedQuantity)) {
            throw new DomainException(sprintf(
                'O saldo físico não pode ficar abaixo do reservado (%s) para ordens de serviço.',
                $this->reserved->toDecimal(),
            ));
        }

        $wasBelowMinimum = $this->isBelowMinimum();
        $previous = $this->physical;
        $increase = $countedQuantity->greaterThan($previous);
        $this->physical = $countedQuantity;

        $this->recordMovement(
            $increase ? MovementType::ADJUSTMENT_IN : MovementType::ADJUSTMENT_OUT,
            $increase ? $countedQuantity->subtract($previous) : $previous->subtract($countedQuantity),
            $reason,
        );
        $this->record(new StockAdjusted($this->id->value, $previous->toDecimal(), $countedQuantity->toDecimal(), $reason));
        $this->detectMinimumReached($wasBelowMinimum);
    }

    /** Verifica, sem alterar nada, se a quantidade pode ser reservada agora. */
    public function canReserve(Quantity $quantity): bool
    {
        return $this->active && $this->unit->accepts($quantity) && $quantity->lessThanOrEqual($this->availableQuantity());
    }

    /** Separa a quantidade para a OS: sai do disponível, continua no físico até a baixa. */
    public function reserve(Quantity $quantity, ServiceOrderReference $serviceOrder): void
    {
        $this->guardActive();
        $this->guardPositive($quantity);

        if ($quantity->greaterThan($this->availableQuantity())) {
            throw new InsufficientStockException([$this->shortageFor($quantity)]);
        }

        $wasBelowMinimum = $this->isBelowMinimum();
        $this->reserved = $this->reserved->add($quantity);

        $this->recordMovement(MovementType::RESERVATION, $quantity, 'Orçamento aprovado', $serviceOrder->value);
        $this->detectMinimumReached($wasBelowMinimum);
    }

    /** Devolve ao disponível uma quantidade reservada (OS cancelada após a aprovação). */
    public function releaseReservation(Quantity $quantity, ServiceOrderReference $serviceOrder): void
    {
        $this->guardPositive($quantity);
        $this->guardReservedCovers($quantity);

        $this->reserved = $this->reserved->subtract($quantity);

        $this->recordMovement(MovementType::RESERVATION_RELEASE, $quantity, 'Ordem de serviço cancelada', $serviceOrder->value);
    }

    /** Baixa definitiva do que estava reservado (serviços finalizados): sai do físico e do reservado. */
    public function consume(Quantity $quantity, ServiceOrderReference $serviceOrder): void
    {
        $this->guardPositive($quantity);
        $this->guardReservedCovers($quantity);

        $this->physical = $this->physical->subtract($quantity);
        $this->reserved = $this->reserved->subtract($quantity);

        $this->recordMovement(MovementType::CONSUMPTION, $quantity, 'Serviços finalizados', $serviceOrder->value);
    }

    /** @return array{item_id: string, sku: string, requested: string, available: string} */
    public function shortageFor(Quantity $requested): array
    {
        return [
            'item_id' => $this->id->value,
            'sku' => $this->sku->value,
            'requested' => $requested->toDecimal(),
            'available' => $this->availableQuantity()->toDecimal(),
        ];
    }

    /**
     * Movimentações geradas desde o último carregamento; o repositório as grava junto com o saldo.
     *
     * @return list<StockMovement>
     */
    public function pullNewMovements(): array
    {
        $movements = $this->newMovements;
        $this->newMovements = [];

        return $movements;
    }

    public function sku(): Sku
    {
        return $this->sku;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function manufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function type(): ItemType
    {
        return $this->type;
    }

    public function unit(): UnitOfMeasure
    {
        return $this->unit;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function physicalQuantity(): Quantity
    {
        return $this->physical;
    }

    public function reservedQuantity(): Quantity
    {
        return $this->reserved;
    }

    public function availableQuantity(): Quantity
    {
        return $this->physical->subtract($this->reserved);
    }

    public function minimumQuantity(): Quantity
    {
        return $this->minimum;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** Disponível <= mínimo: o item entra na lista de reposição. */
    public function isBelowMinimum(): bool
    {
        return $this->availableQuantity()->lessThanOrEqual($this->minimum);
    }

    private function recordMovement(MovementType $type, Quantity $quantity, ?string $reason = null, ?string $reference = null): StockMovement
    {
        $movement = StockMovement::record(
            $this->id,
            $type,
            $quantity,
            $this->physical,
            $this->reserved,
            self::guardText($reason, self::REASON_MAX_LENGTH, 'motivo'),
            self::guardText($reference, self::REFERENCE_MAX_LENGTH, 'referência'),
        );
        $this->newMovements[] = $movement;

        return $movement;
    }

    /** Emite o alerta só na travessia do limite, para não repetir a cada movimentação. */
    private function detectMinimumReached(bool $wasBelowMinimum): void
    {
        if (! $wasBelowMinimum && $this->isBelowMinimum()) {
            $this->record(new MinimumStockReached(
                $this->id->value,
                $this->sku->value,
                $this->availableQuantity()->toDecimal(),
                $this->minimum->toDecimal(),
            ));
        }
    }

    private function guardActive(): void
    {
        if (! $this->active) {
            throw new InactiveInventoryItemException($this->sku->value);
        }
    }

    private function guardPositive(Quantity $quantity): void
    {
        if ($quantity->isZero()) {
            throw new InvalidValueException('A quantidade deve ser maior que zero.');
        }

        self::guardUnit($this->unit, $quantity);
    }

    private function guardReservedCovers(Quantity $quantity): void
    {
        if ($quantity->greaterThan($this->reserved)) {
            throw new DomainException(sprintf(
                'Quantidade maior que a reservada da peça/insumo %s (reservado %s).',
                $this->sku->value,
                $this->reserved->toDecimal(),
            ));
        }
    }

    private static function guardUnit(UnitOfMeasure $unit, Quantity $quantity): void
    {
        if (! $unit->accepts($quantity)) {
            throw new InvalidValueException(sprintf('A unidade %s só aceita quantidades inteiras.', $unit->value));
        }
    }

    private static function guardText(?string $value, int $maxLength, string $field, bool $required = false): ?string
    {
        $value = $value === null ? null : trim((string) preg_replace('/\s+/', ' ', $value));

        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidValueException(sprintf('O campo %s é obrigatório.', $field));
            }

            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidValueException(sprintf('O campo %s deve ter até %d caracteres.', $field, $maxLength));
        }

        return $value;
    }
}
