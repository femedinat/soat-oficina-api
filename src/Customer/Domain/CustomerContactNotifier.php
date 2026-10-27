<?php

declare(strict_types=1);

namespace Oficina\Customer\Domain;

/**
 * Porta de saída para envio de confirmação de atualização de contato do cliente.
 */
interface CustomerContactNotifier
{
    public function sendContactUpdatedConfirmation(Customer $customer): void;
}
