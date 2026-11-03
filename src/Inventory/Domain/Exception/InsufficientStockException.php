<?php

declare(strict_types=1);

namespace Oficina\Inventory\Domain\Exception;

use Oficina\Shared\Domain\Exception\DomainException;

/** Saldo disponível insuficiente. Carrega todos os itens em falta, não só o primeiro. */
final class InsufficientStockException extends DomainException
{
    /** @param list<array{item_id: string, sku: string, requested: string, available: string}> $shortages */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct(sprintf(
            'Estoque insuficiente para: %s.',
            implode(', ', array_map(
                static fn (array $s): string => sprintf('%s (solicitado %s, disponível %s)', $s['sku'], $s['requested'], $s['available']),
                $shortages,
            )),
        ));
    }
}
