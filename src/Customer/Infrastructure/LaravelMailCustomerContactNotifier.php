<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Oficina\Customer\Domain\Customer;
use Oficina\Customer\Domain\CustomerContactNotifier;

/**
 * Adaptador de infraestrutura para envio de confirmação de atualização de contato via e-mail.
 */
final readonly class LaravelMailCustomerContactNotifier implements CustomerContactNotifier
{
    public function __construct(private Mailer $mailer) {}

    public function sendContactUpdatedConfirmation(Customer $customer): void
    {
        $body = sprintf(
            'Olá, %s! Confirmamos que os seus dados de contato na Oficina foram atualizados com sucesso.',
            $customer->name(),
        );

        $this->mailer->raw($body, static function (Message $message) use ($customer): void {
            $message
                ->to($customer->email()->value, $customer->name())
                ->subject('Confirmação de atualização de contato');
        });
    }
}
