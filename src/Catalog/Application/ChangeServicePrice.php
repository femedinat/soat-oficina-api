<?php

declare(strict_types=1);

namespace Oficina\Catalog\Application;

use Oficina\Catalog\Domain\Service;
use Oficina\Shared\Domain\ValueObject\Money;

/** Caso de uso: alterar preço da mão de obra. OS já orçadas mantêm o preço da época. */
final readonly class ChangeServicePrice
{
    public function __construct(private LockedServiceCommand $command) {}

    public function execute(string $id, string $laborPrice): ServiceView
    {
        $price = Money::fromDecimal($laborPrice);

        return $this->command->run($id, static fn (Service $service) => $service->changePrice($price));
    }
}
