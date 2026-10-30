<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;
use Oficina\Catalog\Application\Contract\ServiceCatalog;
use Oficina\Catalog\Application\Contract\ServiceQuote;
use Oficina\ServiceOrder\Domain\ServiceLine;
use Oficina\ServiceOrder\Domain\ServiceOrder;
use Oficina\Shared\Domain\ValueObject\Money;

/** Caso de uso (Fase 4): mecânico encontra reparo extra durante a execução; cliente recebe novo orçamento. */
final readonly class RequestAdditionalRepair
{
    public function __construct(
        private ServiceOrderCommand $command,
        private ServiceCatalog $catalog,
        private PartQuoter $quoter,
    ) {}

    /**
     * @param  list<string>  $serviceIds
     * @param  list<array{inventory_item_id: string, quantity: string}>  $parts
     */
    public function execute(string $orderId, array $serviceIds, array $parts): ServiceOrderView
    {
        $serviceLines = array_map(function (string $serviceId): ServiceLine {
            $quote = $this->catalog->quote($serviceId);

            return self::line($quote);
        }, array_values(array_unique(array_map('strtolower', $serviceIds))));

        $partLines = $this->quoter->quote($parts);

        return $this->command->run(
            $orderId,
            static fn (ServiceOrder $order, DateTimeImmutable $now) => $order->requestAdditionalRepair($serviceLines, $partLines, $now),
        );
    }

    private static function line(ServiceQuote $quote): ServiceLine
    {
        return ServiceLine::quote($quote->serviceId, $quote->code, $quote->name, Money::fromCents($quote->laborPriceCents), $quote->estimatedMinutes, additional: true);
    }
}
