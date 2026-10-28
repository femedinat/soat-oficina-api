<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain;

use Oficina\Shared\Domain\ValueObject\Email;

/**
 * Porta de saída para envio de confirmação de atualização de contato do cliente.
 */
interface CustomerContactNotifier
{
    public function sendContactUpdatedConfirmation(Customer $customer, ?Email $previousEmail = null): void;
}
