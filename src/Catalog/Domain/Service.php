<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain;

use Oficina\Catalog\Domain\Event\ServiceDeactivated;
use Oficina\Catalog\Domain\Event\ServiceEstimatedTimeAdjusted;
use Oficina\Catalog\Domain\Event\ServicePriceChanged;
use Oficina\Catalog\Domain\Event\ServiceReactivated;
use Oficina\Catalog\Domain\Event\ServiceRegistered;
use Oficina\Catalog\Domain\Event\ServiceUpdated;
use Oficina\Catalog\Domain\Event\StandardPartLinked;
use Oficina\Catalog\Domain\Event\StandardPartUnlinked;
use Oficina\Catalog\Domain\Exception\InactiveServiceException;
use Oficina\Shared\Domain\Event\RecordsEvents;
use Oficina\Shared\Domain\Exception\DomainException;
use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Money;

/**
 * Agregado raiz do contexto Catálogo: um serviço oferecido pela oficina (ex.: troca de óleo, alinhamento).
 *
 * Invariantes:
 *  - código imutável e único; nome único (validados na Aplicação + índice no banco);
 *  - tempo estimado entre 1 minuto e 30 dias;
 *  - serviço inativo não entra em orçamento novo nem recebe peça padrão;
 *  - cada peça padrão aparece uma vez (vincular de novo atualiza a quantidade).
 */
final class Service
{
    use RecordsEvents;

    public const int NAME_MAX_LENGTH = 120;

    public const int DESCRIPTION_MAX_LENGTH = 1000;

    public const int CATEGORY_MAX_LENGTH = 60;

    public const int MAX_ESTIMATED_MINUTES = 43_200;

    /** @param array<string, StandardPart> $standardParts indexado pelo id da peça */
    private function __construct(
        public readonly ServiceId $id,
        private readonly ServiceCode $code,
        private string $name,
        private ?string $description,
        private ?string $category,
        private Money $laborPrice,
        private int $estimatedMinutes,
        private array $standardParts,
        private bool $active,
    ) {}

    public static function register(
        ServiceCode $code,
        string $name,
        ?string $description,
        ?string $category,
        Money $laborPrice,
        int $estimatedMinutes,
    ): self {
        $service = new self(
            ServiceId::generate(),
            $code,
            self::guardText($name, self::NAME_MAX_LENGTH, 'nome', required: true) ?? '',
            self::guardText($description, self::DESCRIPTION_MAX_LENGTH, 'descrição'),
            self::guardText($category, self::CATEGORY_MAX_LENGTH, 'categoria'),
            $laborPrice,
            self::guardMinutes($estimatedMinutes),
            [],
            true,
        );

        $service->record(new ServiceRegistered($service->id->value, $code->value, $service->name, $laborPrice->cents));

        return $service;
    }

    /** @param list<StandardPart> $standardParts */
    public static function reconstitute(
        ServiceId $id,
        ServiceCode $code,
        string $name,
        ?string $description,
        ?string $category,
        Money $laborPrice,
        int $estimatedMinutes,
        array $standardParts,
        bool $active,
    ): self {
        $indexed = [];
        foreach ($standardParts as $part) {
            $indexed[$part->inventoryItemId] = $part;
        }

        return new self($id, $code, $name, $description, $category, $laborPrice, $estimatedMinutes, $indexed, $active);
    }

    /** Atualiza nome, descrição e categoria. Preço e tempo têm comandos próprios; código não muda. */
    public function revise(string $name, ?string $description, ?string $category): void
    {
        $this->name = self::guardText($name, self::NAME_MAX_LENGTH, 'nome', required: true) ?? '';
        $this->description = self::guardText($description, self::DESCRIPTION_MAX_LENGTH, 'descrição');
        $this->category = self::guardText($category, self::CATEGORY_MAX_LENGTH, 'categoria');

        $this->record(new ServiceUpdated($this->id->value));
    }

    /** Política: preço congelado nos orçamentos emitidos (a OS guarda uma cópia do preço no item). */
    public function changePrice(Money $laborPrice): void
    {
        if ($laborPrice->equals($this->laborPrice)) {
            return;
        }

        $previous = $this->laborPrice;
        $this->laborPrice = $laborPrice;

        $this->record(new ServicePriceChanged($this->id->value, $previous->cents, $laborPrice->cents));
    }

    public function adjustEstimatedTime(int $minutes): void
    {
        $minutes = self::guardMinutes($minutes);

        if ($minutes === $this->estimatedMinutes) {
            return;
        }

        $previous = $this->estimatedMinutes;
        $this->estimatedMinutes = $minutes;

        $this->record(new ServiceEstimatedTimeAdjusted($this->id->value, $previous, $minutes));
    }

    /** A Aplicação confere antes, no contexto Estoque, se a peça existe e está ativa. */
    public function linkStandardPart(StandardPart $part): void
    {
        if (! $this->active) {
            throw new InactiveServiceException($this->code->value);
        }

        $this->standardParts[$part->inventoryItemId] = $part;

        $this->record(new StandardPartLinked($this->id->value, $part->inventoryItemId, $part->quantity()));
    }

    public function unlinkStandardPart(string $inventoryItemId): void
    {
        $inventoryItemId = strtolower(trim($inventoryItemId));

        if (! isset($this->standardParts[$inventoryItemId])) {
            throw new DomainException('Esta peça/insumo não é padrão do serviço.');
        }

        unset($this->standardParts[$inventoryItemId]);

        $this->record(new StandardPartUnlinked($this->id->value, $inventoryItemId));
    }

    public function hasStandardPart(string $inventoryItemId): bool
    {
        return isset($this->standardParts[strtolower(trim($inventoryItemId))]);
    }

    /** Política: ocultar dos novos orçamentos e manter o histórico nas OS (exclusão lógica). */
    public function deactivate(): void
    {
        if (! $this->active) {
            return;
        }

        $this->active = false;
        $this->record(new ServiceDeactivated($this->id->value));
    }

    public function reactivate(): void
    {
        if ($this->active) {
            return;
        }

        $this->active = true;
        $this->record(new ServiceReactivated($this->id->value));
    }

    /** Usado pela OS ao incluir o serviço: inativo não entra em orçamento novo. */
    public function guardAvailableForBudget(): void
    {
        if (! $this->active) {
            throw new InactiveServiceException($this->code->value);
        }
    }

    public function code(): ServiceCode
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function laborPrice(): Money
    {
        return $this->laborPrice;
    }

    public function estimatedMinutes(): int
    {
        return $this->estimatedMinutes;
    }

    /** @return list<StandardPart> */
    public function standardParts(): array
    {
        return array_values($this->standardParts);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    private static function guardMinutes(int $minutes): int
    {
        if ($minutes < 1 || $minutes > self::MAX_ESTIMATED_MINUTES) {
            throw new InvalidValueException(sprintf('O tempo estimado deve ficar entre 1 e %d minutos.', self::MAX_ESTIMATED_MINUTES));
        }

        return $minutes;
    }

    private static function guardText(?string $value, int $max, string $field, bool $required = false): ?string
    {
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidValueException(sprintf('O campo %s é obrigatório.', $field));
            }

            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidValueException(sprintf('O campo %s aceita no máximo %d caracteres.', $field, $max));
        }

        return $value;
    }
}
