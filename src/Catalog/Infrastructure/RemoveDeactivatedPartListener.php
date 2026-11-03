<?php

declare(strict_types=1);

namespace Oficina\Catalog\Infrastructure;

use Oficina\Catalog\Application\RemoveDeactivatedPartFromServices;
use Oficina\Inventory\Domain\Event\InventoryItemDeactivated;

/** Reage ao evento do Estoque "Peça/Insumo Inativado" (já publicado após o commit). */
final readonly class RemoveDeactivatedPartListener
{
    public function __construct(private RemoveDeactivatedPartFromServices $policy) {}

    public function handle(InventoryItemDeactivated $event): void
    {
        $this->policy->execute($event->itemId);
    }
}
