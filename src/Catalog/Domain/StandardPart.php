<?php

declare(strict_types=1);

namespace Oficina\Catalog\Domain;

use Oficina\Shared\Domain\Exception\InvalidValueException;
use Oficina\Shared\Domain\ValueObject\Uuid;

/**
 * Peça/insumo padrão de um serviço (ex.: troca de óleo -> 4,5 L de óleo + 1 filtro).
 * Referencia a peça do contexto Estoque só pelo id; quantidade em milésimos, como no estoque.
 */
final readonly class StandardPart
{
    private const int MAX_THOUSANDTHS = 999_999_999_999;

    private function __construct(
        public string $inventoryItemId,
        public int $quantityThousandths,
    ) {}

    public static function of(string $inventoryItemId, string $quantity): self
    {
        $id = strtolower(trim($inventoryItemId));

        if (! Uuid::isValid($id)) {
            throw new InvalidValueException('Identificador de peça/insumo inválido.');
        }

        $normalized = str_replace(',', '.', trim($quantity));

        if (! preg_match('/^\d{1,12}(\.\d{1,3})?$/', $normalized)) {
            throw new InvalidValueException('Quantidade inválida: use até 3 casas decimais.');
        }

        [$integer, $fraction] = array_pad(explode('.', $normalized), 2, '');
        $thousandths = ((int) $integer * 1000) + (int) str_pad($fraction, 3, '0');

        if ($thousandths <= 0 || $thousandths > self::MAX_THOUSANDTHS) {
            throw new InvalidValueException('A quantidade da peça padrão deve ser maior que zero.');
        }

        return new self($id, $thousandths);
    }

    public function quantity(): string
    {
        return sprintf('%d.%03d', intdiv($this->quantityThousandths, 1000), $this->quantityThousandths % 1000);
    }
}
