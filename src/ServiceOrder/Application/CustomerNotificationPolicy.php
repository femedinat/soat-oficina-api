<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

use Oficina\ServiceOrder\Domain\CancellationReason;
use Oficina\ServiceOrder\Domain\Event\BudgetSent;
use Oficina\ServiceOrder\Domain\Event\ServiceOrderCanceled;
use Oficina\ServiceOrder\Domain\Event\ServicesFinished;
use Oficina\Shared\Domain\ValueObject\Money;

/**
 * Políticas de notificação do Event Storming (executadas após o commit):
 *  - Orçamento Enviado -> e-mail pedindo aprovação (inicial ou reparo adicional);
 *  - Serviços Finalizados -> e-mail "OS pronta para retirada";
 *  - OS Cancelada -> e-mail informando o motivo.
 */
final readonly class CustomerNotificationPolicy
{
    public function __construct(
        private CustomerDirectory $customers,
        private ServiceOrderNotifier $notifier,
    ) {}

    public function onBudgetSent(BudgetSent $event): void
    {
        $customer = $this->customers->findById($event->customerId);

        if ($customer !== null) {
            $this->notifier->budgetAwaitingApproval($customer, $event->orderId, Money::fromCents($event->totalCents)->toDecimal(), $event->additional, $event->approvalDeadline);
        }
    }

    public function onServicesFinished(ServicesFinished $event): void
    {
        $customer = $this->customers->findById($event->customerId);

        if ($customer !== null) {
            $this->notifier->readyForPickup($customer, $event->orderId);
        }
    }

    public function onCanceled(ServiceOrderCanceled $event): void
    {
        $customer = $this->customers->findById($event->customerId);

        if ($customer !== null) {
            $this->notifier->canceled($customer, $event->orderId, CancellationReason::from($event->reason)->label());
        }
    }
}
