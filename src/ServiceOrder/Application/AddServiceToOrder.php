<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\Catalog\Application\Contract\ServiceCatalog;
use Oficina\ServiceOrder\Domain\ServiceLine;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\Shared\Domain\ValueObject\Money;

/** Caso de uso (Fase 2): adicionar serviço do catálogo à OS, com preço congelado e peças padrão sugeridas. */
final readonly class AddServiceToOrder
{
    public function __construct(
        private ServiceOrderCommand $command,
        private ServiceCatalog $catalog,
    ) {}

    /** @return array{order: ServiceOrderView, suggested_parts: list<array{inventory_item_id: string, quantity: string}>} */
    public function execute(string $orderId, string $serviceId): array
    {
        $quote = $this->catalog->quote($serviceId);
        $line = ServiceLine::quote($quote->serviceId, $quote->code, $quote->name, Money::fromCents($quote->laborPriceCents), $quote->estimatedMinutes);

        $view = $this->command->run($orderId, static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->addService($line, $now));

        // Política "Sugerir Peças Padrão na OS": só consulta, não altera o agregado.
        return ['order' => $view, 'suggested_parts' => $quote->standardParts];
    }
}
