<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use DateTimeImmutable;

/** Porta para o sistema externo de e-mail. Falha de envio nunca desfaz a operação da OS. */
interface ServiceOrderNotifier
{
    public function budgetAwaitingApproval(CustomerSnapshot $customer, string $orderId, string $total, bool $additional, DateTimeImmutable $deadline): void;

    public function readyForPickup(CustomerSnapshot $customer, string $orderId): void;

    public function canceled(CustomerSnapshot $customer, string $orderId, string $reason): void;
}
